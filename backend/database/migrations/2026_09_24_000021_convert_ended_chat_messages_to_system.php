<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "End chat" used to be posted as an ordinary customer message ("Client
     * ended chat."), which made the thread look like it was waiting for a
     * reply (to admin and to a seller in the chat). It's now a system event —
     * convert the ones already sent the same way.
     */
    public function up(): void
    {
        $messages = DB::table('support_messages')->where('body', 'Client ended chat.')->get(['id', 'support_thread_id', 'created_at']);

        foreach ($messages as $message) {
            DB::table('support_messages')->where('id', $message->id)->update([
                'user_id' => null,
                'is_staff' => true,
                'body' => 'Customer ended the chat.',
            ]);

            $thread = DB::table('support_threads')->where('id', $message->support_thread_id)->first(['last_staff_message_at']);
            if ($thread && (! $thread->last_staff_message_at || $thread->last_staff_message_at < $message->created_at)) {
                DB::table('support_threads')->where('id', $message->support_thread_id)->update(['last_staff_message_at' => $message->created_at]);
            }
        }
    }

    public function down(): void
    {
        // Not reversible in a meaningful way; the wording change is harmless.
    }
};
