<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PeruvianDocumentLookupService
{
    public function lookup(string $document): array
    {
        $document = trim($document);
        $documentType = match (strlen($document)) {
            8 => 'DNI',
            11 => 'RUC',
            default => throw new \InvalidArgumentException('El documento debe tener 8 dígitos (DNI) u 11 dígitos (RUC).'),
        };

        $token = (string) config('services.apis_net_pe.token');
        if ($token === '') {
            throw new RuntimeException('La consulta de documentos no está configurada.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->get(rtrim((string) config('services.apis_net_pe.url'), '/') . '/' . strtolower($documentType), [
                'numero' => $document,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('No se pudo consultar el documento.');
        }

        $data = $response->json();

        return [
            'success' => true,
            'document_type' => $documentType,
            'name' => $data['nombre'] ?? null,
            'address' => $data['direccion'] ?? null,
        ];
    }
}