<?php
/**
 * SimpleXLSXGen - Minimal XLSX Writer (No dependencies, uses ZipArchive)
 * Untuk Portal Warga RT 005 GMR 8
 */
class SimpleXLSXGen {
    private $sheets = [];
    private $sharedStrings = [];
    private $sharedIndex = [];

    public function addSheet($sheetName, $data, $headerStyle = true) {
        $this->sheets[] = [
            'name'        => $sheetName,
            'data'        => $data,
            'headerStyle' => $headerStyle,
        ];
        return $this;
    }

    public function saveAs($filename) {
        $xlsx = $this->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($xlsx));
        header('Cache-Control: no-cache');
        echo $xlsx;
    }

    public function saveToDisk($filepath) {
        file_put_contents($filepath, $this->build());
    }

    private function build() {
        // Collect all shared strings first
        $this->sharedStrings = [];
        $this->sharedIndex = [];
        foreach ($this->sheets as $sheet) {
            foreach ($sheet['data'] as $row) {
                foreach ($row as $cell) {
                    if (!is_numeric($cell) && $cell !== null) {
                        $str = (string)$cell;
                        if (!isset($this->sharedIndex[$str])) {
                            $this->sharedIndex[$str] = count($this->sharedStrings);
                            $this->sharedStrings[] = $str;
                        }
                    }
                }
            }
        }

        $zip = new ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip->open($tmpFile, ZipArchive::OVERWRITE);

        // [Content_Types].xml
        $zip->addFromString('[Content_Types].xml', $this->buildContentTypes());

        // _rels/.rels
        $zip->addFromString('_rels/.rels', $this->buildRels());

        // xl/workbook.xml
        $zip->addFromString('xl/workbook.xml', $this->buildWorkbook());

        // xl/_rels/workbook.xml.rels
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->buildWorkbookRels());

        // xl/styles.xml
        $zip->addFromString('xl/styles.xml', $this->buildStyles());

        // xl/sharedStrings.xml
        $zip->addFromString('xl/sharedStrings.xml', $this->buildSharedStrings());

        // xl/worksheets/sheet*.xml
        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->buildWorksheet($sheet));
        }

        // docProps
        $zip->addFromString('docProps/app.xml', $this->buildApp());
        $zip->addFromString('docProps/core.xml', $this->buildCore());

        $zip->close();

        $data = file_get_contents($tmpFile);
        unlink($tmpFile);
        return $data;
    }

    private function buildContentTypes() {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
' . $sheets . '
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>';
    }

    private function buildRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>';
    }

    private function buildWorkbook() {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<sheet name="' . htmlspecialchars($sheet['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>' . $sheets . '</sheets>
</workbook>';
    }

    private function buildWorkbookRels() {
        $rels = '';
        foreach ($this->sheets as $i => $sheet) {
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
        }
        $n = count($this->sheets);
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $rels .= '<Relationship Id="rId' . ($n + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    private function buildStyles() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2">
<font><sz val="11"/><name val="Calibri"/></font>
<font><b/><sz val="11"/><name val="Calibri"/></font>
</fonts>
<fills count="3">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="FF2D6A4F"/></patternFill></fill>
</fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="4">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0"><alignment horizontal="center"/></xf>
<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0"/>
</cellXfs>
</styleSheet>';
    }

    private function buildSharedStrings() {
        $count = count($this->sharedStrings);
        $items = '';
        foreach ($this->sharedStrings as $str) {
            $items .= '<si><t xml:space="preserve">' . htmlspecialchars($str, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">' . $items . '</sst>';
    }

    private function buildWorksheet($sheet) {
        $data = $sheet['data'];
        $headerStyle = $sheet['headerStyle'];
        $rows = '';
        $maxCol = 0;

        foreach ($data as $ri => $row) {
            $rowNum = $ri + 1;
            $cells = '';
            $col = 0;
            foreach ($row as $ci => $cell) {
                $colLetter = $this->colLetter($ci);
                $ref = $colLetter . $rowNum;
                $col = max($col, $ci + 1);

                if ($cell === null || $cell === '') {
                    $cells .= '<c r="' . $ref . '"/>';
                } elseif (is_numeric($cell) && !is_string($cell)) {
                    $styleId = ($ri === 0 && $headerStyle) ? '1' : '0';
                    $cells .= '<c r="' . $ref . '" s="' . $styleId . '"><v>' . $cell . '</v></c>';
                } else {
                    $str = (string)$cell;
                    $sIdx = $this->sharedIndex[$str] ?? 0;
                    $styleId = ($ri === 0 && $headerStyle) ? '1' : '2';
                    $cells .= '<c r="' . $ref . '" t="s" s="' . $styleId . '"><v>' . $sIdx . '</v></c>';
                }
            }
            $maxCol = max($maxCol, $col);
            $rows .= '<row r="' . $rowNum . '">' . $cells . '</row>';
        }

        $dimEnd = $this->colLetter($maxCol - 1) . count($data);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<dimension ref="A1:' . $dimEnd . '"/>
<sheetData>' . $rows . '</sheetData>
</worksheet>';
    }

    private function colLetter($n) {
        $letter = '';
        $n++;
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)) . $letter;
            $n = (int)($n / 26);
        }
        return $letter;
    }

    private function buildApp() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
<Application>Portal Warga RT 005 GMR 8</Application>
</Properties>';
    }

    private function buildCore() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
xmlns:dc="http://purl.org/dc/elements/1.1/">
<dc:creator>Portal Warga RT 005 GMR 8</dc:creator>
<cp:lastModifiedBy>Portal Warga RT 005 GMR 8</cp:lastModifiedBy>
</cp:coreProperties>';
    }
}
