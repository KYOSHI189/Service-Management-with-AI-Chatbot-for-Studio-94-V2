<?php
// ============================================================
// SIMPLE TCPDF - Lightweight PDF Generator
// ============================================================

if (!class_exists('TCPDF')) {

    class TCPDF {
        
        private $content = [];
        private $x = 10;
        private $y = 10;
        private $font = 'helvetica';
        private $fontSize = 12;
        private $fontStyle = '';
        private $lineHeight = 6;
        private $fillColor = [255, 255, 255];
        private $textColor = [0, 0, 0];
        private $cellPadding = 2;
        
        public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {
            $this->content = [];
            $this->x = 10;
            $this->y = 10;
        }
        
        public function setPrintHeader($value) {}
        public function setPrintFooter($value) {}
        
        public function SetCreator($creator) {}
        public function SetAuthor($author) {}
        public function SetTitle($title) {}
        public function SetSubject($subject) {}
        public function SetKeywords($keywords) {}
        
        public function AddPage() {
            $this->y = 10;
            $this->x = 10;
        }
        
        public function SetFont($family, $style = '', $size = 12) {
            $this->font = $family;
            $this->fontStyle = $style;
            $this->fontSize = $size;
        }
        
        public function SetFillColor($r, $g, $b) {
            $this->fillColor = [$r, $g, $b];
        }
        
        public function SetTextColor($r, $g, $b) {
            $this->textColor = [$r, $g, $b];
        }
        
        public function Cell($w, $h, $txt, $border = 0, $ln = 0, $align = 'L', $fill = false) {
            $this->content[] = [
                'type' => 'cell',
                'x' => $this->x,
                'y' => $this->y,
                'w' => $w,
                'h' => $h,
                'txt' => $txt,
                'border' => $border,
                'align' => $align,
                'fill' => $fill
            ];
            $this->x += $w;
            if ($ln == 1) {
                $this->y += $h;
                $this->x = 10;
            }
        }
        
        public function Ln($h = null) {
            if ($h === null) {
                $h = $this->lineHeight;
            }
            $this->y += $h;
            $this->x = 10;
        }
        
        public function writeHTML($html) {}
        
        public function Output($name, $dest = 'I') {
            // Build HTML table
            $html = '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; font-size: 11px; margin: 40px; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .title { font-size: 18px; font-weight: bold; }
                    .subtitle { font-size: 14px; font-weight: bold; }
                    .info { font-size: 10px; color: #666; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                    th { background: #ddd; padding: 6px; border: 1px solid #000; text-align: center; font-weight: bold; }
                    td { padding: 5px; border: 1px solid #000; }
                    .footer { text-align: center; font-size: 9px; color: #666; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; }
                    .total { text-align: right; font-weight: bold; font-size: 12px; margin-top: 10px; }
                    .bg-gray { background: #f5f5f5; }
                </style>
            </head>
            <body>';
            
            // Build table
            $html .= '<table>';
            $fill = false;
            $rowCount = 0;
            
            foreach ($this->content as $cell) {
                if ($cell['type'] === 'cell') {
                    if ($rowCount === 0) {
                        // Header row
                        $html .= '<tr>';
                    }
                    
                    $align = $cell['align'] === 'C' ? 'center' : ($cell['align'] === 'R' ? 'right' : 'left');
                    $style = $cell['fill'] ? 'background:#f5f5f5;' : '';
                    $html .= '<td style="' . $style . 'text-align:' . $align . ';padding:4px;border:1px solid #000;">' . $cell['txt'] . '</td>';
                    
                    if ($cell['border'] == 1 || $cell['border'] == 'LR') {
                        // End of row
                        $html .= '</tr>';
                        $rowCount = 0;
                        $fill = !$fill;
                    } else {
                        $rowCount++;
                    }
                }
            }
            
            $html .= '</table>';
            $html .= '</body></html>';
            
            // Output
            if ($dest === 'D') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $name . '"');
            }
            
            // Simple PDF output using HTML
            echo '<html><head>
                <style>
                    body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #000; padding: 5px; text-align: left; }
                    th { background: #ddd; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .title { font-size: 20px; font-weight: bold; }
                    .subtitle { font-size: 16px; font-weight: bold; }
                    .footer { text-align: center; font-size: 10px; color: #666; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; }
                </style>
            </head>
            <body>';
            
            // Rebuild table for output
            echo '<div class="header"><div class="title">STUDIO 94 SNAPTRACK</div>';
            echo '<div class="subtitle">' . htmlspecialchars($name) . '</div>';
            echo '<div class="info">Generated: ' . date('F d, Y h:i A') . '</div></div>';
            
            echo '<table>';
            $rowCount = 0;
            $cols = [];
            
            foreach ($this->content as $cell) {
                if ($cell['type'] === 'cell') {
                    if ($rowCount === 0) {
                        echo '<tr>';
                    }
                    echo '<td>' . $cell['txt'] . '</td>';
                    $rowCount++;
                    if ($cell['border'] == 1 || $cell['border'] == 'LR') {
                        echo '</tr>';
                        $rowCount = 0;
                    }
                }
            }
            
            echo '</table>';
            echo '<div class="footer">This report was generated by Studio 94 SnapTrack. All rights reserved.</div>';
            echo '</body></html>';
            
            exit;
        }
    }
}