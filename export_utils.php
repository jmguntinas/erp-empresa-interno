<?php
// export_utils.php
// Utilidades para exportar a CSV, Excel (XML 2003) y PDF (requiere libs/fpdf.php).

function export_csv(array $rows, array $headers, string $filename='export.csv'): void {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  $out = fopen('php://output', 'w');
  fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 (Excel friendly)
  fputcsv($out, array_values($headers), ';');
  foreach ($rows as $r) {
    $line=[];
    foreach(array_keys($headers) as $k){ $line[] = isset($r[$k]) ? $r[$k] : ''; }
    fputcsv($out, $line, ';');
  }
  fclose($out); exit;
}

function export_xlsx(array $rows, array $headers, string $filename='export.xls'): void {
  header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  echo '<?xml version="1.0"?>'."\n";
  echo '<?mso-application progid="Excel.Sheet"?>'."\n";
  echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                 xmlns:o="urn:schemas-microsoft-com:office:office"
                 xmlns:x="urn:schemas-microsoft-com:office:excel"
                 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";
  echo '<Styles>
          <Style ss:ID="header"><Font ss:Bold="1"/><Interior ss:Color="#D9E1F2" ss:Pattern="Solid"/></Style>
          <Style ss:ID="num"><NumberFormat ss:Format="0.00"/></Style>
          <Style ss:ID="text"></Style>
        </Styles>';
  echo '<Worksheet ss:Name="Datos"><Table>';
  // Cabecera
  echo '<Row>';
  foreach($headers as $head){ echo '<Cell ss:StyleID="header"><Data ss:Type="String">'.htmlspecialchars($head).'</Data></Cell>'; }
  echo '</Row>';
  // Filas
  foreach($rows as $r){
    echo '<Row>';
    foreach(array_keys($headers) as $k){
      $v = isset($r[$k]) ? $r[$k] : '';
      if (is_numeric($v)) {
        echo '<Cell ss:StyleID="num"><Data ss:Type="Number">'.(0+$v).'</Data></Cell>';
      } else {
        echo '<Cell ss:StyleID="text"><Data ss:Type="String">'.htmlspecialchars((string)$v).'</Data></Cell>';
      }
    }
    echo '</Row>';
  }
  echo '</Table></Worksheet></Workbook>';
  exit;
}

function export_pdf(array $rows, array $headers, string $filename='export.pdf', string $title='Listado'): void {
  $fpdf = __DIR__ . '/../libs/fpdf.php';
  if (!file_exists($fpdf)) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Falta libs/fpdf.php para exportar a PDF. Descárgalo de https://www.fpdf.org/ y colócalo en /libs/fpdf.php";
    exit;
  }
  require_once $fpdf;
  $pdf = new FPDF('L','mm','A4');
  $pdf->AddPage(); $pdf->SetFont('Arial','B',14);
  $pdf->Cell(0,10,utf8_decode($title),0,1,'C');
  // Cabecera
  $pdf->SetFont('Arial','B',9);
  $widths = []; $minWidth = 30; $maxWidth=80;
  foreach($headers as $h){ $widths[] = max($minWidth, min($maxWidth, 180 / max(1,count($headers)))); }
  foreach(array_values($headers) as $i=>$h){ $pdf->Cell($widths[$i],8,utf8_decode($h),1,0,'C'); }
  $pdf->Ln();
  // Filas
  $pdf->SetFont('Arial','',9);
  foreach($rows as $r){
    $i=0;
    foreach(array_keys($headers) as $k){
      $txt = isset($r[$k]) ? (string)$r[$k] : '';
      $pdf->Cell($widths[$i],7,utf8_decode(mb_strimwidth($txt,0,40,'…','UTF-8')),1);
      $i++;
    }
    $pdf->Ln();
  }
  $pdf->Output('D',$filename);
  exit;
}
