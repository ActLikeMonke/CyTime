<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserDataExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDataExportController extends Controller
{
    public function __invoke(Request $request, UserDataExportService $exports, string $format): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $data = $exports->forUser($user);
        $date = now()->format('Ymd');

        if ($format === 'json') {
            return response()->streamDownload(
                fn () => print json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                "cytime-data-export-{$date}.json",
                ['Content-Type' => 'application/json; charset=UTF-8'],
            );
        }

        return response()->streamDownload(function () use ($data): void {
            $stream = fopen('php://output', 'wb');
            if (! is_resource($stream)) {
                throw new \RuntimeException('Could not open the data export stream.');
            }

            fputcsv($stream, ['record_type', 'record_id', 'data_json'], ',', '"', '\\');
            fputcsv($stream, ['profile', $data['profile']['id'], json_encode($data['profile'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)], ',', '"', '\\');

            foreach (['work_schedules', 'time_entries', 'absences', 'audit_history'] as $collection) {
                foreach ($data[$collection] as $record) {
                    fputcsv($stream, [$collection, $record['id'] ?? $record['record_id'], json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)], ',', '"', '\\');
                }
            }

            fclose($stream);
        }, "cytime-data-export-{$date}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
