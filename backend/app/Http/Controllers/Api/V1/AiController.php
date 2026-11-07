<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiToolLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/*AI HR Attendance Agent

  Bersifat READ-ONLY dan hanya menjawab pertanyaan agregat kehadiran,
  Endpoint siap disambungkan ke LLM eksternal kalo gak ada LLM yang
  dikonfigurasi, agen menjawab berbasis aturan bawaan*/
class AiController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|integer|exists:ai_conversations,id',
        ]);

        $user = $request->user();

        $conversation = $data['conversation_id'] ?? null
            ? AiConversation::where('user_id', $user->getAuthIdentifier())->findOrFail($data['conversation_id'])
            : AiConversation::create([
                'user_id' => $user->getAuthIdentifier(),
                'title' => Str::limit($data['message'], 60),
            ]);

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'USER',
            'content' => $data['message'],
        ]);

        [$answer, $toolName, $labels] = $this->answer($data['message'], $conversation);

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'ASSISTANT',
            'content' => $answer,
            'classification_labels' => $labels,
        ]);

        return $this->success([
            'conversation_id' => $conversation->id,
            'tool' => $toolName,
            'labels' => $labels,
            'answer' => $answer,
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $query = AiConversation::query()
            ->withCount('messages')
            ->where('user_id', $request->user()->getAuthIdentifier());


        return $this->paginated($query->orderByDesc('created_at')->paginate($request->integer('per_page', 20)));
    }

    public function messages(Request $request, int $id): JsonResponse
        {
            $conversation = AiConversation::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->findOrFail($id);

            return $this->success(
            $conversation->messages()->orderBy('id')->get()
            );
        }

    /* jawaban berbasis aturan (fallback tanpa LLM)
      cuma baca data tidak pernah menulis selain log percakapan */
    protected function answer(
        string $message,
        AiConversation $conversation
        ): array
    {
        $text = mb_strtolower($message);
        $tool = 'none';
        $labels = ['GENERAL'];

        // Pertanyaan: siapa yang alpha / gk masuk tanpa keterangan hari ini
        if (str_contains($text, 'alpha') || str_contains($text, 'tanpa keterangan') || str_contains($text, 'tidak masuk')) {
            $date = now()->toDateString();
            $count = AttendanceRecord::where('work_date', $date)
                ->where('status', AttendanceRecord::STATUS_ABSENT)
                ->count();

            $tool = 'count_absent';
            $labels = ['ATTENDANCE', 'ALPHA'];

            AiToolLog::create([
                'conversation_id' => $conversation->id,
                'tool_name' => $tool,
                'parameters' => ['date' => $date],
                'result_summary' => "{$count} karyawan alpha",
            ]);

            return [
                "Pada {$date} terdapat {$count} karyawan berstatus Tanpa Keterangan (Alpha). "
                .'Setiap hari alpha dikenakan potongan langsung 4% tanpa jatah toleransi.',
                $tool,
                $labels,
            ];
        }

        // Pertanyaan: rekap kehadiran hari ini
        if (str_contains($text, 'hadir') || str_contains($text, 'kehadiran') || str_contains($text, 'rekap')) {
            $date = now()->toDateString();
            $rows = AttendanceRecord::where('work_date', $date)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $tool = 'attendance_summary';
            $labels = ['ATTENDANCE'];

            AiToolLog::create([
                'conversation_id' => $conversation->id,
                'tool_name' => $tool,
                'parameters' => ['date' => $date],
                'result_summary' => json_encode($rows),
            ]);

            $ringkasan = $rows->map(fn ($t, $s) => "{$s}: {$t}")->implode(', ');

            return [
                "Rekap kehadiran {$date} — {$ringkasan}.",
                $tool,
                $labels,
            ];
        }

        // Pertanyaan: jumlah karyawan
        if (str_contains($text, 'karyawan') || str_contains($text, 'pegawai')) {
            $total = Employee::where('employment_status', Employee::STATUS_ACTIVE)->count();

            $tool = 'count_employees';
            $labels = ['EMPLOYEE'];

            return ["Jumlah karyawan aktif saat ini: {$total} orang.", $tool, $labels];
        }

        $tool = 'fallback';

        return [
            'Agen HR hanya dapat menjawab pertanyaan seputar kehadiran, alpha/tanpa keterangan, '
            .'rekap harian, dan jumlah karyawan. Sambungkan LLM pada konfigurasi untuk cakupan penuh.',
            $tool,
            $labels,
        ];
    }
}
