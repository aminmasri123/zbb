<?php

namespace App\Http\Controllers;

use App\Models\AiWorkspaceRun;
use App\Services\Ai\AgentClient;
use App\Services\Ai\PdfTextExtractor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class AiWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        AiWorkspaceRun::query()->where('created_at', '<=', now()->subDays(7))->delete();

        return Inertia::render('Ai/Workspace', [
            'runs' => AiWorkspaceRun::query()->where('user_id', $request->user()->id)->latest()->limit(20)->get(),
            'retentionDays' => 7,
            'knowledgeLabel' => 'Kein Live-Wissen · Informationen können veraltet sein',
            'modelLabel' => 'Wissensstichtag nicht verlässlich datierbar',
        ]);
    }

    public function generate(Request $request, AgentClient $agent, PdfTextExtractor $pdfs)
    {
        set_time_limit(240);
        $data=$request->validate(['task'=>['required',Rule::in(['chat','summarize','compare','image_analysis'])],'instruction'=>['required','string','max:8000'],'documents'=>['nullable','array','max:2'],'documents.*'=>['file','mimes:pdf','max:10240'],'image'=>['nullable','file','mimes:jpg,jpeg,png,webp','max:5120']]);
        if(in_array($data['task'],['summarize','compare'],true) && count($request->file('documents',[])) < ($data['task']==='compare'?2:1)) abort(422,'Bitte wählen Sie die benötigten PDF-Dateien aus.');
        if($data['task']==='image_analysis' && !$request->hasFile('image')) abort(422,'Bitte wählen Sie ein Bild aus.');
        $sources=[];$metadata=[];$budget=120000;
        foreach($request->file('documents',[]) as $index=>$file){$metadata[]=['name'=>$file->getClientOriginalName(),'size'=>$file->getSize(),'mime'=>$file->getMimeType()];foreach($pdfs->pages($file->getRealPath()) as $page){if($budget<=0)break 2;$text=mb_substr($page['text'],0,min(18000,$budget));$sources[]=['source_id'=>'document-'.($index+1).'-page-'.$page['page'],'label'=>$file->getClientOriginalName(),'page'=>$page['page'],'text'=>$text];$budget-=mb_strlen($text);}}
        $image=null;if($data['task']==='image_analysis'&&$request->hasFile('image')){$file=$request->file('image');$metadata[]=['name'=>$file->getClientOriginalName(),'size'=>$file->getSize(),'mime'=>$file->getMimeType()];$image=$this->optimizedImageBase64($file->getRealPath());}
        $uuid=(string)Str::uuid();$result=$agent->generate(['run_id'=>$uuid,'task'=>$data['task'],'instruction'=>$data['instruction'],'sources'=>$sources,'image_base64'=>$image]);
        $run=AiWorkspaceRun::create(['user_id'=>$request->user()->id,'run_uuid'=>$uuid,'task'=>$data['task'],'instruction'=>$data['instruction'],'source_metadata'=>$metadata,'title'=>$result['title'],'content'=>$result['content'],'citations'=>$result['citations'],'warnings'=>$result['warnings'],'status'=>'completed']);
        return response()->json(['run'=>$run]);
    }

    public function export(Request $request, AiWorkspaceRun $run, string $format)
    {
        abort_unless((int)$run->user_id===(int)$request->user()->id,404);abort_unless(in_array($format,['pdf','docx'],true),404);
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
}
