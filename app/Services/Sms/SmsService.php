<?php
declare(strict_types=1);
namespace App\Services\Sms;

use App\Services\AuditLogger;
final class SmsService
{
    public function queue(string $title, string $type, array $recipients, ?string $scheduledAt, ?int $createdBy, ?int $templateId = null): int
    {
        $db = db_connect(); $now = date('Y-m-d H:i:s'); $renderer = new TemplateRenderer();$eligible=[];
        foreach($recipients as $item){$number=format_ph_mobile((string)($item['number']??''));$body=$renderer->render((string)($item['body']??''),$item['values']??[]);if($number!==''&&trim($body)!==''){$item['number']=$number;$item['body']=$body;$eligible[]=$item;}}
        if($eligible===[])throw new \InvalidArgumentException('At least one valid recipient and non-empty message are required.');
        $db->transBegin();
        $db->table('sms_batches')->insert(['title' => $title, 'message_type' => $type, 'template_id' => $templateId, 'recipient_count' => count($eligible), 'scheduled_at' => $scheduledAt, 'created_by' => $createdBy, 'created_at' => $now, 'updated_at' => $now]);
        $batchId = (int) $db->insertID();
        foreach ($eligible as $item) {
            $number = $item['number'];
            $body = $item['body'];
            $db->table('sms_messages')->insert([
                'batch_id' => $batchId, 'guardian_id' => $item['guardian_id'] ?? null, 'child_id' => $item['child_id'] ?? null,
                'recipient_number' => $number, 'body' => $body, 'segments' => $renderer->segments($body),
                'status' => $scheduledAt && strtotime($scheduledAt) > time() ? 'scheduled' : 'queued', 'scheduled_at' => $scheduledAt,
                'reminder_key' => $item['reminder_key'] ?? null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            if (! empty($item['guardian_id'])) {
                $guardian = $db->table('guardians')->select('user_id')->where('id', $item['guardian_id'])->get()->getRowArray();
                if (! empty($guardian['user_id'])) $db->table('notifications')->insert(['user_id'=>$guardian['user_id'],'type'=>$type,'title'=>$title,'body'=>$body,'url'=>$item['child_id']?site_url('parent/children/'.$item['child_id']):null,'created_at'=>$now,'updated_at'=>$now]);
            }
        }
        $db->transComplete();
        if ($db->transStatus() === false) throw new \RuntimeException('Unable to queue SMS batch.');
        (new AuditLogger())->log('sms_queued', 'sms_batch', $batchId, null, ['count' => count($eligible)], $createdBy);
        return $batchId;
    }
}
