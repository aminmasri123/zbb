<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAiWorkspaceJob;
use App\Models\AiWorkspaceRun;
use App\Services\Ai\AgentClient;
use App\Services\Ai\PdfTextExtractor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

class AiWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        AiWorkspaceRun::query()->where('created_at', '<=', now()->subDays(7))->delete();

        return Inertia::render('Ai/Workspace', [
            'runs' => AiWorkspaceRun::query()->where('user_id', $request->user()->id)->latest()->limit(20)->get(),
            'retentionDays' => 7,
            'knowledgeLabel' => 'Kein Live-Wissen · Informationen können veraltet sein',
            'modelLabel' => 'Text: qwen3:1.7b · Bild: qwen3-vl:2b · Wissensstichtag nicht verlässlich datierbar',
        ]);
    }

    public function generate(Request $request, PdfTextExtractor $pdfs)
    {
        $data = $request->validate([
            'task' => ['required', Rule::in(['chat', 'summarize', 'compare', 'image_analysis'])],
            'instruction' => ['required', 'string', 'max:8000'],
            'documents' => ['nullable', 'array', 'max:2'],
            'documents.*' => ['file', 'mimes:pdf', 'max:10240'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        if (in_array($data['task'], ['summarize', 'compare'], true)
            && count($request->file('documents', [])) < ($data['task'] === 'compare' ? 2 : 1)) {
            throw ValidationException::withMessages(['documents' => 'Bitte wählen Sie die benötigten PDF-Dateien aus.']);
        }
        if ($data['task'] === 'image_analysis' && !$request->hasFile('image')) {
            throw ValidationException::withMessages(['image' => 'Bitte wählen Sie ein Bild aus.']);
        }

        try {
            [$sources, $metadata] = $this->documentSources($request, $pdfs);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['documents' => $exception->getMessage()]);
        }

        $image = null;
        if ($data['task'] === 'image_analysis' && $request->hasFile('image')) {
            $file = $request->file('image');
            $metadata[] = [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];
            $image = $this->optimizedImageBase64($file->getRealPath());
        }

        $uuid = (string) Str::uuid();
        $run = AiWorkspaceRun::create([
            'user_id' => $request->user()->id,
            'run_uuid' => $uuid,
            'task' => $data['task'],
            'instruction' => $data['instruction'],
            'source_metadata' => $metadata,
            'request_payload' => [
                'run_id' => $uuid,
                'task' => $data['task'],
                'instruction' => $data['instruction'],
                'sources' => $sources,
                'image_base64' => $image,
            ],
            'status' => 'queued',
            'progress_percent' => 0,
        ]);

        $connection = (string) config('queue.ai_workspace_connection', config('queue.default', 'sync'));
        GenerateAiWorkspaceJob::dispatch($uuid)->onConnection($connection);
        $run->refresh();

        return response()->json([
            'status' => $run->status,
            'run_id' => $uuid,
            'status_url' => route('ai.workspace.status', $uuid),
            'run' => $this->runPayload($run),
        ], $run->status === 'queued' ? 202 : 200, ['Cache-Control' => 'no-store, private']);
    }

    public function status(Request $request, string $run)
    {
        $runModel = AiWorkspaceRun::query()
            ->where('run_uuid', $run)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $elapsedFrom = $runModel->started_at ?? $runModel->created_at;
        $elapsedSeconds = max(0, (int) floor($elapsedFrom->diffInSeconds(now(), true)));
        $progress = (int) $runModel->progress_percent;
        if ($runModel->status === 'running') {
            $progress = max($progress, min(90, 15 + (int) floor($elapsedSeconds * 1.25)));
        }

        $estimatedRemainingSeconds = null;
        if (in_array($runModel->status, ['queued', 'running'], true) && $runModel->started_at) {
            $averageSeconds = AiWorkspaceRun::query()
                ->where('user_id', $request->user()->id)
                ->where('task', $runModel->task)
                ->where('status', 'completed')
                ->whereNotNull('duration_seconds')
                ->where('created_at', '>=', now()->subDays(14))
                ->avg('duration_seconds');
            if (is_numeric($averageSeconds)) {
                $estimatedRemainingSeconds = (int) max(0, round((float) $averageSeconds - $elapsedSeconds));
            }
        }

        return response()->json([
            'run_id' => $runModel->run_uuid,
            'status' => $runModel->status,
            'status_label' => match ($runModel->status) {
                'queued' => 'Warte auf KI-Verarbeitung',
                'running' => match ($runModel->task) {
                    'summarize' => 'PDF wird zusammengefasst',
                    'compare' => 'Dokumente werden verglichen',
                    'image_analysis' => 'Bild wird analysiert',
                    default => 'Antwort wird erstellt',
                },
                'completed' => 'Fertig',
                'failed' => 'Fehlgeschlagen',
                default => 'Unbekannt',
            },
            'progress_percent' => $progress,
            'elapsed_seconds' => $elapsedSeconds,
            'estimated_remaining_seconds' => $estimatedRemainingSeconds,
            'queue_warning' => $runModel->status === 'queued' && $elapsedSeconds >= 30
                ? 'Der Auftrag wartet noch. Bitte den Queue-Worker auf dem Webserver prüfen.'
                : null,
            'error_message' => $runModel->status === 'failed'
                ? 'Der KI-Dienst konnte die Anfrage nicht verarbeiten.'
                : null,
            'run' => $this->runPayload($runModel),
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function export(Request $request, AiWorkspaceRun $run, string $format)
    {
        abort_unless((int)$run->user_id===(int)$request->user()->id,404);abort_unless(in_array($format,['pdf','docx'],true),404);
        abort_unless($run->status === 'completed' && filled($run->content), 409, 'Das KI-Ergebnis ist noch nicht fertig.');
        if($format==='pdf')return Pdf::loadView('pdf.ai-workspace',['run'=>$run])->setPaper('a4')->download(str($run->title)->slug().'.pdf');
        $word=new PhpWord();$section=$word->addSection();$section->addTitle($run->title,1);foreach(preg_split('/\R{2,}/',$run->content) as $paragraph)$section->addText($paragraph);if($run->citations){$section->addTitle('Quellen',2);foreach($run->citations as $citation)$section->addListItem(($citation['source_id']??'Quelle').(isset($citation['page'])?' – Seite '.$citation['page']:''));}$path=tempnam(sys_get_temp_dir(),'zbb-ai-');IOFactory::createWriter($word,'Word2007')->save($path);return response()->download($path,str($run->title)->slug().'.docx')->deleteFileAfterSend(true);
    }

    public function destroy(Request $request, AiWorkspaceRun $run)
    {
        abort_unless((int) $run->user_id === (int) $request->user()->id, 404);
        $run->delete();

        return response()->noContent();
    }

    public function destroyAll(Request $request)
    {
        AiWorkspaceRun::query()->where('user_id', $request->user()->id)->delete();

        return response()->noContent();
    }

    private function optimizedImageBase64(string $path): string
    {
        $raw = file_get_contents($path);
        $size = getimagesize($path);
        abort_if($raw === false || $size === false || ($size[0] * $size[1]) > 40_000_000, 422, 'Das Bild ist zu groß oder ungültig.');
        $source = @imagecreatefromstring($raw);
        abort_if($source === false, 422, 'Das Bild konnte nicht verarbeitet werden.');
        $maximum = 1280;
        $scale = min(1, $maximum / max(imagesx($source), imagesy($source)));
        $width = max(1, (int) round(imagesx($source) * $scale));
        $height = max(1, (int) round(imagesy($source) * $scale));
        $optimized = imagescale($source, $width, $height, IMG_BILINEAR_FIXED);
        imagedestroy($source);
        abort_if($optimized === false, 422, 'Das Bild konnte nicht verkleinert werden.');
        ob_start();
        imagejpeg($optimized, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($optimized);
        abort_if(! is_string($jpeg), 422, 'Das Bild konnte nicht kodiert werden.');

        return base64_encode($jpeg);
    }

    private function documentSources(Request $request, PdfTextExtractor $pdfs): array
    {
        $pages = [];
        $metadata = [];
        foreach ($request->file('documents', []) as $index => $file) {
            $documentPages = $pdfs->pages($file->getRealPath());
            $metadata[] = [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'pages' => count($documentPages),
            ];
            foreach ($documentPages as $page) {
                $pages[] = [
                    'source_id' => 'document-' . ($index + 1) . '-page-' . $page['page'],
                    'label' => $file->getClientOriginalName(),
                    'page' => $page['page'],
                    'text' => $page['text'],
                ];
            }
        }

        $perPageBudget = $pages === [] ? 0 : max(180, min(5000, (int) floor(24_000 / count($pages))));
        $sources = array_map(function (array $page) use ($perPageBudget): array {
            $page['text'] = $this->balancedExcerpt($page['text'], $perPageBudget);
            return $page;
        }, $pages);

        return [$sources, $metadata];
    }

    private function balancedExcerpt(string $text, int $maximum): string
    {
        if ($maximum < 1 || mb_strlen($text) <= $maximum) {
            return $text;
        }
        $separator = ' […] ';
        $headLength = (int) floor(($maximum - mb_strlen($separator)) * 0.65);
        $tailLength = max(1, $maximum - mb_strlen($separator) - $headLength);

        return mb_substr($text, 0, $headLength) . $separator . mb_substr($text, -$tailLength);
    }

    private function runPayload(AiWorkspaceRun $run): array
    {
        return $run->only([
            'id', 'run_uuid', 'task', 'instruction', 'source_metadata', 'title',
            'content', 'citations', 'warnings', 'status', 'progress_percent',
            'duration_seconds', 'started_at', 'completed_at', 'created_at', 'updated_at',
        ]);
    }
}
