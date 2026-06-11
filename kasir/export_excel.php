<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'kasir') {
    header('Location: ../../login.php'); exit;
}
require_once '../config/db.php';

// ── Sanitasi parameter tanggal ─────────────────────────────────────────────
$dari   = (isset($_GET['dari'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari']))   ? $_GET['dari']   : date('Y-m-d');
$sampai = (isset($_GET['sampai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'])) ? $_GET['sampai'] : date('Y-m-d');
if ($dari > $sampai) { $t = $dari; $dari = $sampai; $sampai = $t; }

// ── Queries ────────────────────────────────────────────────────────────────

$stm = $pdo->prepare("SELECT COUNT(*) AS jml, COALESCE(SUM(total_harga),0) AS total FROM penjualan WHERE DATE(tgl_penjualan) BETWEEN ? AND ?");
$stm->execute([$dari, $sampai]);
$ring = $stm->fetch();

$pdo->exec("SET group_concat_max_len = 20000");
$stm = $pdo->prepare(
    "SELECT p.id_penjualan,
            p.tgl_penjualan,
            COALESCE(p.nama_pelanggan, u.nama, 'Pelanggan Umum') AS nama_pelanggan,
            COALESCE(
                GROUP_CONCAT(CONCAT(pr.nama_produk,' x',dp.jumlah) ORDER BY pr.nama_produk SEPARATOR '; '),
                '(tidak ada detail)'
            ) AS produk,
            p.total_harga
     FROM penjualan p
     LEFT JOIN user u  ON p.id_user    = u.id_user
     LEFT JOIN detail_penjualan dp ON p.id_penjualan = dp.id_penjualan
     LEFT JOIN produk pr ON dp.id_produk = pr.id_produk
     WHERE DATE(p.tgl_penjualan) BETWEEN ? AND ?
     GROUP BY p.id_penjualan, p.tgl_penjualan, nama_pelanggan, p.total_harga
     ORDER BY p.tgl_penjualan ASC"
);
$stm->execute([$dari, $sampai]);
$transaksi = $stm->fetchAll();

$stm = $pdo->prepare(
    "SELECT pr.nama_produk,
            SUM(dp.jumlah)    AS total_terjual,
            SUM(dp.subtotal)  AS total_pendapatan
     FROM detail_penjualan dp
     JOIN produk   pr ON dp.id_produk   = pr.id_produk
     JOIN penjualan p  ON dp.id_penjualan = p.id_penjualan
     WHERE DATE(p.tgl_penjualan) BETWEEN ? AND ?
     GROUP BY dp.id_produk, pr.nama_produk
     ORDER BY total_terjual DESC"
);
$stm->execute([$dari, $sampai]);
$per_produk = $stm->fetchAll();

$stm = $pdo->prepare(
    "SELECT DATE_FORMAT(tgl_penjualan,'%Y-%m') AS bulan,
            COUNT(*)         AS jml_trx,
            SUM(total_harga) AS total
     FROM penjualan
     WHERE DATE(tgl_penjualan) BETWEEN ? AND ?
     GROUP BY DATE_FORMAT(tgl_penjualan,'%Y-%m')
     ORDER BY bulan ASC"
);
$stm->execute([$dari, $sampai]);
$per_bulan = $stm->fetchAll();

// ── Helpers ────────────────────────────────────────────────────────────────
function xe($s) { return htmlspecialchars((string)$s, ENT_XML1, 'UTF-8'); }
function xC($val, $type = 'String', $style = '') {
    $sa = $style ? " ss:StyleID=\"{$style}\"" : '';
    if ($type === 'Number') return "<Cell{$sa}><Data ss:Type=\"Number\">" . (is_numeric($val) ? $val : 0) . "</Data></Cell>";
    return "<Cell{$sa}><Data ss:Type=\"String\">" . xe($val) . "</Data></Cell>";
}
function xBlank($n = 1) { return str_repeat('<Cell><Data ss:Type="String"></Data></Cell>', $n); }

// ── Kalkulasi ──────────────────────────────────────────────────────────────
$avg_trx   = (int)$ring['jml'] > 0 ? round($ring['total'] / $ring['jml']) : 0;
$grand_ttl = array_sum(array_column($transaksi, 'total_harga'));
$period_lb = date('d M Y', strtotime($dari)) . ' s.d. ' . date('d M Y', strtotime($sampai));

// ── HTTP Headers ───────────────────────────────────────────────────────────
$filename = 'Laporan_Transaksi_EleaStore_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

print '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
print '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Title>Laporan Transaksi Kasir Elea Store</Title><Author>Elea Store Kasir</Author>
 </DocumentProperties>
 <Styles>
  <Style ss:ID="Default"><Font ss:FontName="Calibri" ss:Size="11"/></Style>
  <Style ss:ID="s_store_title">
   <Font ss:FontName="Calibri" ss:Size="18" ss:Bold="1" ss:Color="#7a2e22"/>
  </Style>
  <Style ss:ID="s_subtitle"><Font ss:FontName="Calibri" ss:Size="10" ss:Color="#9ca3af"/></Style>
  <Style ss:ID="s_section_hdr">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#1f2937"/>
   <Interior ss:Color="#fff8f6" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s_label"><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#6b7280"/></Style>
  <Style ss:ID="s_val"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#1f2937"/></Style>
  <Style ss:ID="s_stat_num">
   <Font ss:FontName="Calibri" ss:Size="20" ss:Bold="1" ss:Color="#1f2937"/>
  </Style>
  <Style ss:ID="s_stat_rp">
   <Font ss:FontName="Calibri" ss:Size="20" ss:Bold="1" ss:Color="#7a2e22"/>
   <NumberFormat ss:Format="&quot;Rp &quot;#,##0"/>
  </Style>
  <Style ss:ID="s_stat_rp_sm">
   <Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#7a2e22"/>
   <NumberFormat ss:Format="&quot;Rp &quot;#,##0"/>
  </Style>
  <Style ss:ID="s_th_blue">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#7a2e22" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  </Style>
  <Style ss:ID="s_th_teal">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#9e5848" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  </Style>
  <Style ss:ID="s_th_indigo">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
   <Interior ss:Color="#7a2e22" ss:Pattern="Solid"/>
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
  </Style>
  <Style ss:ID="s_td"><Font ss:FontName="Calibri" ss:Size="11"/><Alignment ss:Vertical="Center" ss:WrapText="1"/></Style>
  <Style ss:ID="s_td_c"><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#6b7280"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>
  <Style ss:ID="s_rp"><Font ss:FontName="Calibri" ss:Size="11"/><NumberFormat ss:Format="&quot;Rp &quot;#,##0"/></Style>
  <Style ss:ID="s_z"><Font ss:FontName="Calibri" ss:Size="11"/><Interior ss:Color="#f0f9ff" ss:Pattern="Solid"/><Alignment ss:Vertical="Center" ss:WrapText="1"/></Style>
  <Style ss:ID="s_z_c"><Font ss:FontName="Calibri" ss:Size="11" ss:Color="#6b7280"/><Interior ss:Color="#f0f9ff" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>
  <Style ss:ID="s_z_rp"><Font ss:FontName="Calibri" ss:Size="11"/><Interior ss:Color="#f0f9ff" ss:Pattern="Solid"/><NumberFormat ss:Format="&quot;Rp &quot;#,##0"/></Style>
  <Style ss:ID="s_total_rp">
   <Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1" ss:Color="#7a2e22"/>
   <Interior ss:Color="#e0f2fe" ss:Pattern="Solid"/>
   <NumberFormat ss:Format="&quot;Rp &quot;#,##0"/>
   <Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#7a2e22"/></Borders>
  </Style>
  <Style ss:ID="s_total_num">
   <Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1" ss:Color="#7a2e22"/>
   <Interior ss:Color="#e0f2fe" ss:Pattern="Solid"/>
   <Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#7a2e22"/></Borders>
  </Style>
  <Style ss:ID="s_total_lbl">
   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#7a2e22"/>
   <Interior ss:Color="#e0f2fe" ss:Pattern="Solid"/>
   <Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#7a2e22"/></Borders>
  </Style>
 </Styles>

 <!-- ════════════════════════════════════════════════════════════════════ -->
 <!-- SHEET 1 · RINGKASAN                                                 -->
 <!-- ════════════════════════════════════════════════════════════════════ -->
 <Worksheet ss:Name="Ringkasan">
  <Table ss:DefaultColumnWidth="160">
   <Column ss:Width="210"/><Column ss:Width="280"/>
   <Row ss:Height="40"><?= xC('ELEA STORE', 'String', 's_store_title') ?></Row>
   <Row><?= xC('Toko Fashion for All', 'String', 's_subtitle') ?></Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>
   <Row ss:Height="22"><Cell ss:StyleID="s_section_hdr" ss:MergeAcross="1"><Data ss:Type="String">  INFORMASI LAPORAN</Data></Cell></Row>
   <Row><?= xC('Periode',       'String', 's_label') ?><?= xC($period_lb,                   'String', 's_val') ?></Row>
   <Row><?= xC('Tanggal Cetak', 'String', 's_label') ?><?= xC(date('d M Y, H:i').' WIB',   'String', 's_val') ?></Row>
   <Row><?= xC('Kasir',         'String', 's_label') ?><?= xC(xe($_SESSION['user']['nama']).' · Kasir', 'String', 's_val') ?></Row>
   <Row><Cell><Data ss:Type="String"></Data></Cell></Row>
   <Row ss:Height="22"><Cell ss:StyleID="s_section_hdr" ss:MergeAcross="1"><Data ss:Type="String">  RINGKASAN KINERJA</Data></Cell></Row>
   <Row ss:Height="44"><?= xC('Total Transaksi',         'String', 's_label') ?><?= xC((int)$ring['jml'],     'Number', 's_stat_num')   ?></Row>
   <Row ss:Height="44"><?= xC('Total Pendapatan',        'String', 's_label') ?><?= xC((float)$ring['total'], 'Number', 's_stat_rp')    ?></Row>
   <Row ss:Height="36"><?= xC('Rata-rata per Transaksi', 'String', 's_label') ?><?= xC((float)$avg_trx,       'Number', 's_stat_rp_sm') ?></Row>
   <Row ss:Height="36"><?= xC('Produk Berbeda Terjual',  'String', 's_label') ?><?= xC(count($per_produk),   'Number', 's_stat_num')   ?></Row>
  </Table>
 </Worksheet>

 <!-- ════════════════════════════════════════════════════════════════════ -->
 <!-- SHEET 2 · DETAIL TRANSAKSI                                          -->
 <!-- ════════════════════════════════════════════════════════════════════ -->
 <Worksheet ss:Name="Detail Transaksi">
  <Table>
   <Column ss:Width="36"/><Column ss:Width="80"/><Column ss:Width="86"/>
   <Column ss:Width="58"/><Column ss:Width="160"/><Column ss:Width="260"/><Column ss:Width="130"/>
   <Row ss:Height="26">
    <?= xC('No.',              'String', 's_th_blue') ?>
    <?= xC('ID Transaksi',     'String', 's_th_blue') ?>
    <?= xC('Tanggal',          'String', 's_th_blue') ?>
    <?= xC('Waktu',            'String', 's_th_blue') ?>
    <?= xC('Nama Pelanggan',   'String', 's_th_blue') ?>
    <?= xC('Produk Dibeli',    'String', 's_th_blue') ?>
    <?= xC('Total Harga (Rp)', 'String', 's_th_blue') ?>
   </Row>
   <?php foreach ($transaksi as $i => $t):
     $ev=$i%2===0; $td=$ev?'s_td':'s_z'; $tc=$ev?'s_td_c':'s_z_c'; $rp=$ev?'s_rp':'s_z_rp';
   ?>
   <Row>
    <?= xC($i+1, 'Number', $tc) ?>
    <?= xC('#'.str_pad($t['id_penjualan'],5,'0',STR_PAD_LEFT), 'String', $td) ?>
    <?= xC(date('d/m/Y', strtotime($t['tgl_penjualan'])), 'String', $td) ?>
    <?= xC(date('H:i',   strtotime($t['tgl_penjualan'])), 'String', $td) ?>
    <?= xC($t['nama_pelanggan'], 'String', $td) ?>
    <?= xC($t['produk'],         'String', $td) ?>
    <?= xC((float)$t['total_harga'], 'Number', $rp) ?>
   </Row>
   <?php endforeach; ?>
   <?php if (!empty($transaksi)): ?>
   <Row ss:Height="24">
    <?= xBlank(5) ?>
    <?= xC('TOTAL', 'String', 's_total_lbl') ?>
    <?= xC((float)$grand_ttl, 'Number', 's_total_rp') ?>
   </Row>
   <?php endif; ?>
  </Table>
 </Worksheet>

 <!-- ════════════════════════════════════════════════════════════════════ -->
 <!-- SHEET 3 · PER PRODUK                                                -->
 <!-- ════════════════════════════════════════════════════════════════════ -->
 <Worksheet ss:Name="Per Produk">
  <Table>
   <Column ss:Width="36"/><Column ss:Width="230"/><Column ss:Width="130"/><Column ss:Width="150"/>
   <Row ss:Height="26">
    <?= xC('No.',                   'String', 's_th_teal') ?>
    <?= xC('Nama Produk',           'String', 's_th_teal') ?>
    <?= xC('Total Terjual (unit)',   'String', 's_th_teal') ?>
    <?= xC('Total Pendapatan (Rp)', 'String', 's_th_teal') ?>
   </Row>
   <?php $tot_unit=0; $tot_pend=0; ?>
   <?php foreach ($per_produk as $i => $p):
     $ev=$i%2===0; $td=$ev?'s_td':'s_z'; $tc=$ev?'s_td_c':'s_z_c'; $rp=$ev?'s_rp':'s_z_rp';
     $tot_unit+=(int)$p['total_terjual']; $tot_pend+=(float)$p['total_pendapatan'];
   ?>
   <Row>
    <?= xC($i+1, 'Number', $tc) ?>
    <?= xC($p['nama_produk'], 'String', $td) ?>
    <?= xC((int)$p['total_terjual'], 'Number', $td) ?>
    <?= xC((float)$p['total_pendapatan'], 'Number', $rp) ?>
   </Row>
   <?php endforeach; ?>
   <?php if (!empty($per_produk)): ?>
   <Row ss:Height="24">
    <?= xBlank(2) ?>
    <?= xC($tot_unit, 'Number', 's_total_num') ?>
    <?= xC($tot_pend, 'Number', 's_total_rp')  ?>
   </Row>
   <?php endif; ?>
  </Table>
 </Worksheet>

 <!-- ════════════════════════════════════════════════════════════════════ -->
 <!-- SHEET 4 · PER BULAN                                                 -->
 <!-- ════════════════════════════════════════════════════════════════════ -->
 <Worksheet ss:Name="Per Bulan">
  <Table>
   <Column ss:Width="36"/><Column ss:Width="140"/><Column ss:Width="130"/>
   <Column ss:Width="150"/><Column ss:Width="170"/>
   <Row ss:Height="26">
    <?= xC('No.',                      'String', 's_th_indigo') ?>
    <?= xC('Bulan',                    'String', 's_th_indigo') ?>
    <?= xC('Jumlah Transaksi',         'String', 's_th_indigo') ?>
    <?= xC('Total Pendapatan (Rp)',    'String', 's_th_indigo') ?>
    <?= xC('Rata-rata/Transaksi (Rp)', 'String', 's_th_indigo') ?>
   </Row>
   <?php $tot_trx=0; $tot_pend_b=0; ?>
   <?php foreach ($per_bulan as $i => $b):
     $ev=$i%2===0; $td=$ev?'s_td':'s_z'; $tc=$ev?'s_td_c':'s_z_c'; $rp=$ev?'s_rp':'s_z_rp';
     $avgB=$b['jml_trx']>0?round($b['total']/$b['jml_trx']):0;
     $tot_trx+=(int)$b['jml_trx']; $tot_pend_b+=(float)$b['total'];
   ?>
   <Row>
    <?= xC($i+1, 'Number', $tc) ?>
    <?= xC(date('F Y', strtotime($b['bulan'].'-01')), 'String', $td) ?>
    <?= xC((int)$b['jml_trx'],  'Number', $td) ?>
    <?= xC((float)$b['total'],  'Number', $rp) ?>
    <?= xC((float)$avgB,        'Number', $rp) ?>
   </Row>
   <?php endforeach; ?>
   <?php if (!empty($per_bulan)): ?>
   <Row ss:Height="24">
    <?= xBlank(2) ?>
    <?= xC($tot_trx,    'Number', 's_total_num') ?>
    <?= xC($tot_pend_b, 'Number', 's_total_rp')  ?>
    <?= xBlank(1) ?>
   </Row>
   <?php endif; ?>
  </Table>
 </Worksheet>
</Workbook>
