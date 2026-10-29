<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function index()
    {
        return view('system.index');
    }

    public function resetData(Request $request)
    {
        return back()->with('error', 'El reinicio maestro está deshabilitado permanentemente en esta instalación.');
    }

    public function backup()
    {
        try {
            $dbName = env('DB_DATABASE');
            $dbUser = env('DB_USERNAME');
            $dbPass = env('DB_PASSWORD');
            $dbHost = env('DB_HOST', '127.0.0.1');

            $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filePath = storage_path('app/' . $fileName);

            $passwordParam = empty($dbPass) ? '' : '--password="' . $dbPass . '"';
            $command = "mysqldump --user=\"{$dbUser}\" {$passwordParam} --host=\"{$dbHost}\" {$dbName} > \"{$filePath}\"";

            $output = [];
            $returnVar = null;
            exec($command, $output, $returnVar);

            if ($returnVar !== 0) {
                return back()->with('error', 'No se pudo generar el backup. Verifica que mysqldump esté en el PATH.');
            }

            return response()->download($filePath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar la copia de seguridad: ' . $e->getMessage());
        }
    }
}
