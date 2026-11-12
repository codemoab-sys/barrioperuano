<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class BillingExcelExportService
{
    private const HEADERS = [
        'Fecha de emisión',
        'Tipo de comprobante',
        'Serie',
        'Número',
        'Tipo de documento',
        'RUC / DNI',
        'Cliente / Razón social',
        'Base imponible gravada (S/)',
        'IGV (S/)',
        'Operaciones exoneradas (S/)',
        'Operaciones inafectas (S/)',
        'Operaciones gratuitas (S/)',
        'Descuento (S/)',
        'Importe total (S/)',
        'Moneda',
        'Medio de pago',
        'Estado SUNAT',
        'Código SUNAT',
        'Estado de anulación',
        'Motivo de anulación',
        'Fecha de envío',
        'Observación SUNAT',
    ];

    public function create(Collection $orders, string $from, string $to, array $filters): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor no tiene habilitada la extensión ZIP requerida para generar archivos Excel.');
        }

        $path = tempnam(sys_get_temp_dir(), 'billing-report-');
        if ($path === false) {
            throw new RuntimeException('No se pudo preparar el archivo Excel.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }

        try {
            $this->addEntry($zip, '[Content_Types].xml', $this->contentTypesXml());
            $this->addEntry($zip, '_rels/.rels', $this->rootRelationshipsXml());
            $this->addEntry($zip, 'xl/workbook.xml', $this->workbookXml());
            $this->addEntry($zip, 'xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
            $this->addEntry($zip, 'xl/styles.xml', $this->stylesXml());
            $this->addEntry($zip, 'xl/worksheets/sheet1.xml', $this->worksheetXml($orders, $from, $to, $filters));
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($path);
            throw $exception;
        }

        if (!$zip->close()) {
            @unlink($path);
            throw new RuntimeException('No se pudo finalizar el archivo Excel.');
        }

        return $path;
    }

    private function addEntry(ZipArchive $zip, string $name, string $contents): void
    {
        if (!$zip->addFromString($name, $contents)) {
            throw new RuntimeException("No se pudo agregar '{$name}' al archivo Excel.");
        }
    }

    private function worksheetXml(Collection $orders, string $from, string $to, array $filters): string
    {
        $rows = [];
        $rows[] = $this->rowXml(1, [$this->textCell('Reporte de comprobantes electrónicos', 1)]);
        $rows[] = $this->rowXml(2, [$this->textCell("Periodo: {$from} al {$to}")]);

        $filterLabels = array_filter([
            'Tipo: ' . ($filters['type'] ?? 'Todos'),
            'Estado SUNAT: ' . ($filters['status'] ?? 'Todos'),
            'Búsqueda: ' . ($filters['q'] ?? 'Sin filtro'),
        ], fn (string $label) => !str_ends_with($label, 'Todos') && !str_ends_with($label, 'Sin filtro'));
        if ($filterLabels !== []) {
            $rows[] = $this->rowXml(3, [$this->textCell(implode(' | ', $filterLabels))]);
        }

        $headerRow = 4;
        $rows[] = $this->rowXml($headerRow, array_map(fn (string $header) => $this->textCell($header, 1), self::HEADERS));

        $amountColumns = [7, 8, 9, 10, 11, 12, 13];
        $totals = array_fill_keys($amountColumns, 0.0);
        $rowNumber = $headerRow + 1;

        foreach ($orders as $order) {
            $documentNumber = (string) ($order->client_document ?? '');
            $documentType = match (strlen(preg_replace('/\D/', '', $documentNumber))) {
                8 => '1 - DNI',
                11 => '6 - RUC',
                default => '- - Varios',
            };
            $documentCode = $order->document_type === 'Factura' ? '01' : '03';
            $issuedAt = $order->issued_at ?? $order->created_at;
            $sentAt = $order->sent_at;
            $amounts = [
                (float) ($order->total_gravada ?? $order->subtotal ?? 0),
                (float) ($order->igv ?? 0),
                (float) ($order->total_exonerada ?? 0),
                (float) ($order->total_inafecta ?? 0),
                (float) ($order->total_gratuita ?? 0),
                (float) ($order->discount ?? 0),
                (float) ($order->total ?? 0),
            ];
            $dateIssued = $issuedAt ? Carbon::parse($issuedAt) : null;
            $dateSent = $sentAt ? Carbon::parse($sentAt) : null;
            $values = [
                $this->dateCell($dateIssued),
                $this->textCell($documentCode . ' - ' . $order->document_type),
                $this->textCell((string) $order->serie),
                $this->textCell((string) $order->correlativo),
                $this->textCell($documentType),
                $this->textCell($documentNumber ?: '-'),
                $this->textCell((string) ($order->client_name ?: 'Cliente')),
            ];
            foreach ($amounts as $index => $amount) {
                $column = $amountColumns[$index];
                $totals[$column] += $amount;
                $values[] = $this->numberCell($amount, 3);
            }

            $values[] = $this->textCell('PEN');
            $values[] = $this->textCell($this->paymentLabel($order->payment_method ?? null));
            $values[] = $this->textCell((string) ($order->sunat_status ?? ''));
            $values[] = $this->textCell((string) ($order->sunat_code ?? ''));
            $values[] = $this->textCell((string) ($order->anulacion_status ?? ''));
            $values[] = $this->textCell((string) ($order->anulacion_description ?? ''));
            $values[] = $this->dateCell($dateSent);
            $values[] = $this->textCell((string) ($order->sunat_description ?? ''));
            $rows[] = $this->rowXml($rowNumber, $values);
            $rowNumber++;
        }

        $totalCells = [$this->textCell('TOTAL DEL REPORTE', 2)];
        for ($column = 1; $column < count(self::HEADERS); $column++) {
            $totalCells[] = array_key_exists($column, $totals)
                ? $this->numberCell($totals[$column], 4)
                : $this->textCell('', 2);
        }
        $rows[] = $this->rowXml($rowNumber, $totalCells);

        $lastDataRow = max($headerRow, $rowNumber - 1);
        $lastColumn = $this->columnName(count(self::HEADERS));
        $filterRange = "A{$headerRow}:{$lastColumn}{$lastDataRow}";
        $dimension = "A1:{$lastColumn}{$rowNumber}";

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="' . $dimension . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $headerRow
            . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols><col min="1" max="1" width="20" customWidth="1"/>'
            . '<col min="2" max="6" width="18" customWidth="1"/>'
            . '<col min="7" max="7" width="36" customWidth="1"/>'
            . '<col min="8" max="14" width="20" customWidth="1"/>'
            . '<col min="15" max="18" width="18" customWidth="1"/>'
            . '<col min="19" max="21" width="26" customWidth="1"/>'
            . '<col min="22" max="22" width="52" customWidth="1"/></cols>'
            . '<sheetData>' . implode('', $rows) . '</sheetData>'
            . '<autoFilter ref="' . $filterRange . '"/>'
            . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '</worksheet>';
    }

    private function rowXml(int $number, array $cells): string
    {
        $xml = '<row r="' . $number . '">';
        foreach ($cells as $index => $cell) {
            $xml .= '<c r="' . $this->columnName($index + 1) . $number . '"' . $cell . '</c>';
        }

        return $xml . '</row>';
    }

    private function textCell(string $value, int $style = 0): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
        $value = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return ' s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $value . '</t></is>';
    }

    private function numberCell(float $value, int $style): string
    {
        return ' s="' . $style . '"><v>' . number_format($value, 2, '.', '') . '</v>';
    }

    private function dateCell(?Carbon $date): string
    {
        if ($date === null) {
            return $this->textCell('');
        }

        $wallClock = strtotime($date->format('Y-m-d H:i:s') . ' UTC');
        if ($wallClock === false) {
            return $this->textCell('');
        }

        $excelDate = ($wallClock / 86400) + 25569;

        return ' s="5"><v>' . number_format($excelDate, 10, '.', '') . '</v>';
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)) . $name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function paymentLabel(?string $payment): string
    {
        return match ($payment) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            'yape' => 'Yape',
            'plin' => 'Plin',
            default => '',
        };
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Comprobantes" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00;[Red]-#,##0.00"/>'
            . '<numFmt numFmtId="165" formatCode="dd/mm/yyyy hh:mm"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
