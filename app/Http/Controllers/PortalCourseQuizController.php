<?php

namespace App\Http\Controllers;

use App\Models\PortalCourseEnrollment;
use App\Models\PortalCourseQuiz;
use App\Models\PortalQuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PortalCourseQuizController extends Controller
{
    public function index(Request $request)
    {
        $enrollments = PortalCourseEnrollment::query()->where('status', '!=', 'cancelled')
            ->whereHas('participation', fn ($query) => $query->where('personen_id', $request->user()->person_id))
            ->with(['course.project:id,name', 'participation.projekt'])->get()
            ->filter(fn ($item) => $item->participation->projekt->portalFeatureEnabled('learning') && $item->course->status === 'published')->values();
        $quizzes = PortalCourseQuiz::query()->whereIn('course_id', $enrollments->pluck('course_id'))->where('published', true)
            ->with(['course:id,title,project_id', 'questions.options' => fn ($query) => $query->select(['id', 'question_id', 'label', 'sort_order']), 'attempts' => fn ($query) => $query->whereIn('enrollment_id', $enrollments->pluck('id'))->orderBy('attempt_number')])
            ->orderBy('sort_order')->get();
        return Inertia::render('ParticipantPortal/Quizzes', ['enrollments' => $enrollments, 'quizzes' => $quizzes]);
    }

    public function submit(Request $request, PortalCourseQuiz $quiz)
    {
        $enrollment = $this->enrollment($request, $quiz);
        $data = $request->validate(['answers' => ['present', 'array'], 'answers.*' => ['array'], 'answers.*.*' => ['integer']]);
        $quiz->load('questions.options');
        abort_if($quiz->questions->isEmpty(), 422, 'Das Quiz enthält noch keine Fragen.');

        $attempt = DB::transaction(function () use ($quiz, $enrollment, $data) {
            $locked = PortalCourseQuiz::query()->lockForUpdate()->findOrFail($quiz->id);
            $count = PortalQuizAttempt::query()->where('quiz_id', $quiz->id)->where('enrollment_id', $enrollment->id)->lockForUpdate()->count();
            abort_if($count >= $locked->max_attempts, 422, 'Die maximale Anzahl an Versuchen ist erreicht.');
            $earned = 0.0; $max = (float) $quiz->questions->sum(fn ($question) => (float) $question->points); $evaluated = [];
            foreach ($quiz->questions as $question) {
                $selected = collect($data['answers'][$question->id] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values();
                $allowed = $question->options->pluck('id')->map(fn ($id) => (int) $id);
                if ($selected->diff($allowed)->isNotEmpty()) throw ValidationException::withMessages(['answers' => 'Eine Antwortoption gehört nicht zur Frage.']);
                $correct = $question->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
                $isCorrect = $selected->all() === $correct->all(); $points = $isCorrect ? (float) $question->points : 0.0; $earned += $points;
                $evaluated[] = [$question, $selected->all(), $isCorrect, $points];
            }
            $percentage = $max > 0 ? round($earned / $max * 100, 2) : 0;
            $attempt = PortalQuizAttempt::query()->create(['quiz_id' => $quiz->id, 'enrollment_id' => $enrollment->id, 'attempt_number' => $count + 1, 'earned_points' => $earned, 'max_points' => $max, 'percentage' => $percentage, 'passed' => $percentage >= $quiz->passing_percent, 'submitted_at' => now()]);
            foreach ($evaluated as [$question, $selected, $correct, $points]) $attempt->answers()->create(['question_id' => $question->id, 'selected_option_ids' => $selected, 'correct' => $correct, 'earned_points' => $points]);
            return $attempt;
        });
        return response()->json(['message' => $attempt->passed ? 'Quiz bestanden.' : 'Quiz abgeschlossen. Bestehensgrenze noch nicht erreicht.', 'attempt' => $attempt], 201);
    }

    private function enrollment(Request $request, PortalCourseQuiz $quiz): PortalCourseEnrollment
    {
        abort_unless($quiz->published && $quiz->course->status === 'published', 404);
        $enrollment = PortalCourseEnrollment::query()->where('course_id', $quiz->course_id)->where('status', '!=', 'cancelled')
            ->whereHas('participation', fn ($query) => $query->where('personen_id', $request->user()->person_id))->with('participation.projekt')->firstOrFail();
        abort_unless($enrollment->participation->projekt->portalFeatureEnabled('learning'), 404);
        return $enrollment;
    }
}
