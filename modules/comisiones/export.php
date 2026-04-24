<?php
// modules/comisiones/export.php — Exports as a styled Excel XML (SpreadsheetML)
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!can_access_module('comisiones', $pdo)) {
    die("Acceso denegado.");
}

/* -------- Filter parameters (same as index.php) -------- */
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$tech_filter = strval($_GET['tech_id'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$is_admin = can_access_module('comisiones_add', $pdo);
$user_id = $_SESSION['user_id'];

$params = [];
$where = [];

if (!$is_admin) {
    $where[] = "c.tech_id = ?";
    $params[] = $user_id;
} elseif ($tech_filter !== '') {
    $where[] = "c.tech_id = ?";
    $params[] = $tech_filter;
}
if ($search) {
    $where[] = "(c.cliente LIKE ? OR c.servicio LIKE ? OR c.caso LIKE ? OR c.factura LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
}
if ($status_filter) {
    $where[] = "c.estado = ?";
    $params[] = $status_filter;
}

if ($date_from) {
    $where[] = "c.fecha_servicio >= ?";
    $params[] = $date_from . ' 00:00:00';
}
if ($date_to) {
    $where[] = "c.fecha_servicio <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $pdo->prepare("
    SELECT
        c.caso, c.tipo, c.fecha_servicio, c.fecha_facturacion,
        c.cliente, c.lugar, c.servicio, c.cantidad,
        c.factura, c.vendedor,
        COALESCE(u.full_name, u.username) AS tecnico,
        c.estado, c.notas
    FROM comisiones c
    LEFT JOIN users u ON c.tech_id = u.id
    $where_clause
    ORDER BY c.fecha_servicio DESC, c.id DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get technician name for the filename if tech_filter is active or if not admin
$tech_display_name = '';
if (!$is_admin) {
    $tech_display_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Tecnico';
} elseif ($tech_filter) {
    $stTech = $pdo->prepare("SELECT COALESCE(full_name, username) FROM users WHERE id = ?");
    $stTech->execute([$tech_filter]);
    $tech_display_name = $stTech->fetchColumn();
}
$tech_suffix = $tech_display_name ? '_' . str_replace(' ', '_', $tech_display_name) : '';

/* -------- Helper: escape XML characters -------- */
function xesc(string $v): string
{
    return htmlspecialchars($v, ENT_XML1, 'UTF-8');
}

/* -------- Build filter description for the report subheader -------- */
$filter_parts = [];
if ($search)
    $filter_parts[] = "Búsqueda: " . $search;
if ($status_filter)
    $filter_parts[] = "Estado: " . $status_filter;
if ($tech_filter)
    $filter_parts[] = "Tech ID: " . $tech_filter;
if ($date_from || $date_to) {
    $d1 = $date_from ? date('d/m/Y', strtotime($date_from)) : 'Inicio';
    $d2 = $date_to ? date('d/m/Y', strtotime($date_to)) : 'Fin';
    $filter_parts[] = "Fecha: $d1 al $d2";
}
$filter_desc = $filter_parts ? implode(' | ', $filter_parts) : 'Todos los registros';
$exported_on = date('d/m/Y H:i');
$total_rows = count($rows);

/* -------- Column definitions -------- */
$columns = [
    ['label' => 'Caso / Proyecto', 'width' => 22],
    ['label' => 'Tipo', 'width' => 12],
    ['label' => 'Técnico Asignado', 'width' => 25],
    ['label' => 'Nº Factura', 'width' => 18],
    ['label' => 'Cliente', 'width' => 30],
    ['label' => 'Fecha Servicio', 'width' => 16],
    ['label' => 'Fecha Facturación', 'width' => 18],
    ['label' => 'Lugar / Zona', 'width' => 22],
    ['label' => 'Descripción del Servicio', 'width' => 45],
    ['label' => 'Cantidad', 'width' => 10],
    ['label' => 'Vendedor / Captador', 'width' => 22],
    ['label' => 'Estado Comisión', 'width' => 16],
    ['label' => 'Observaciones / Notas', 'width' => 40],
];

/* -------- Stream Excel XML -------- */
$filename = 'Mis_Incentivos' . $tech_suffix . '_' . date('Y-m-d_Hi') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Byte-order mark
echo "\xEF\xBB\xBF";

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:html="http://www.w3.org/TR/REC-html40">

    <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
        <Author>System Taller</Author>
        <Title>Comisiones RRHH</Title>
        <Created><?php echo date('c'); ?></Created>
    </DocumentProperties>

    <Styles>
        <!-- Title row -->
        <Style ss:ID="sTitle">
            <Font ss:Bold="1" ss:Size="14" ss:Color="#FFFFFF" /><Interior ss:Color="#1E293B" ss:Pattern="Solid" /><Alignment ss:Horizontal="Left" ss:Vertical="Center" />
        </Style>
        <!-- Sub-title / meta row -->
        <Style ss:ID="sMeta">
            <Font ss:Bold="0" ss:Size="10" ss:Color="#94A3B8" /><Interior ss:Color="#0F172A" ss:Pattern="Solid" /><Alignment ss:Horizontal="Left" ss:Vertical="Center" />
        </Style>
        <!-- Column headers -->
        <Style ss:ID="sHeader">
            <Font ss:Bold="1" ss:Size="10" ss:Color="#FFFFFF" /><Interior ss:Color="#6366F1" ss:Pattern="Solid" /><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#4F46E5" /></Borders>
        </Style>
        <!-- Normal data cell -->
        <Style ss:ID="sData">
            <Font ss:Size="9" ss:Color="#1E293B" /><Alignment ss:Vertical="Center" ss:WrapText="0" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0" /></Borders>
        </Style>
        <!-- Stripped (alternate) row -->
        <Style ss:ID="sDataAlt">
            <Font ss:Size="9" ss:Color="#1E293B" /><Interior ss:Color="#F8FAFC" ss:Pattern="Solid" /><Alignment ss:Vertical="Center" ss:WrapText="0" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0" /></Borders>
        </Style>
        <!-- PENDIENTE badge cell -->
        <Style ss:ID="sPendiente">
            <Font ss:Size="9" ss:Bold="1" ss:Color="#92400E" /><Interior ss:Color="#FEF3C7" ss:Pattern="Solid" /><Alignment ss:Horizontal="Center" ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0" /></Borders>
        </Style>
        <!-- PAGADA badge cell -->
        <Style ss:ID="sPagada">
            <Font ss:Size="9" ss:Bold="1" ss:Color="#065F46" /><Interior ss:Color="#D1FAE5" ss:Pattern="Solid" /><Alignment ss:Horizontal="Center" ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0" /></Borders>
        </Style>
        <!-- PROYECTO type -->
        <Style ss:ID="sProyecto">
            <Font ss:Size="9" ss:Bold="1" ss:Color="#3730A3" /><Interior ss:Color="#EEF2FF" ss:Pattern="Solid" /><Alignment ss:Horizontal="Center" ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0" /></Borders>
        </Style>
        <!-- SERVICIO type -->
        <Style ss:ID="sServicio">
            <Font ss:Size="9" ss:Bold="1" ss:Color="#065F46" /><Interior ss:Color="#ECFDF5" ss:Pattern="Solid" /><Alignment ss:Horizontal="Center" ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0" /></Borders>
        </Style>
        <!-- Number / quantity cell -->
        <Style ss:ID="sNumber">
            <Font ss:Size="9" ss:Color="#1E293B" /><Alignment ss:Horizontal="Center" ss:Vertical="Center" /><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0" /></Borders><NumberFormat ss:Format="0" />
        </Style>
        <!-- Summary / totals row -->
        <Style ss:ID="sSummary">
            <Font ss:Bold="1" ss:Size="10" ss:Color="#1E293B" /><Interior ss:Color="#EEF2FF" ss:Pattern="Solid" /><Alignment ss:Horizontal="Left" ss:Vertical="Center" /><Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#6366F1" /></Borders>
        </Style>
    </Styles>

    <Worksheet ss:Name="Comisiones RRHH">
        <Table ss:ExpandedColumnCount="<?php echo count($columns); ?>"
            ss:ExpandedRowCount="<?php echo $total_rows + 6; ?>" x:FullColumns="1" x:FullRows="1"
            ss:DefaultRowHeight="18">

            <?php foreach ($columns as $col): ?>
                <Column ss:Width="<?php echo $col['width'] * 7; ?>" />
            <?php endforeach; ?>

            <!-- ===== TITLE ROW ===== -->
            <Row ss:Height="28">
                <Cell ss:StyleID="sTitle" ss:MergeAcross="<?php echo count($columns) - 1; ?>">
                    <Data ss:Type="String">🗂 Reporte de Comisiones — System Taller</Data>
                </Cell>
            </Row>

            <!-- ===== META ROW 1: filter info ===== -->
            <Row ss:Height="16">
                <Cell ss:StyleID="sMeta" ss:MergeAcross="<?php echo count($columns) - 1; ?>">
                    <Data ss:Type="String">Filtro aplicado: <?php echo xesc($filter_desc); ?></Data>
                </Cell>
            </Row>

            <!-- ===== META ROW 2: export date & totals ===== -->
            <Row ss:Height="16">
                <Cell ss:StyleID="sMeta" ss:MergeAcross="<?php echo count($columns) - 1; ?>">
                    <Data ss:Type="String">Exportado el: <?php echo $exported_on; ?> | Total registros:
                        <?php echo $total_rows; ?></Data>
                </Cell>
            </Row>

            <!-- ===== BLANK SEPARATOR ===== -->
            <Row ss:Height="8">
                <Cell ss:StyleID="sMeta" ss:MergeAcross="<?php echo count($columns) - 1; ?>">
                    <Data ss:Type="String"></Data>
                </Cell>
            </Row>

            <!-- ===== COLUMN HEADERS ===== -->
            <Row ss:Height="30">
                <?php foreach ($columns as $col): ?>
                    <Cell ss:StyleID="sHeader">
                        <Data ss:Type="String"><?php echo xesc($col['label']); ?></Data>
                    </Cell>
                <?php endforeach; ?>
            </Row>

            <!-- ===== DATA ROWS ===== -->
            <?php
            $rowIdx = 0;
            foreach ($rows as $r):
                $rowIdx++;
                $alt = ($rowIdx % 2 === 0) ? 'sDataAlt' : 'sData';

                $fecha_srv = $r['fecha_servicio'] ? date('d/m/Y', strtotime($r['fecha_servicio'])) : '';
                $fecha_fac = $r['fecha_facturacion'] ? date('d/m/Y', strtotime($r['fecha_facturacion'])) : 'Pendiente';

                $tipo_style = ($r['tipo'] === 'PROYECTO') ? 'sProyecto' : 'sServicio';
                $estado_style = ($r['estado'] === 'PAGADA') ? 'sPagada' : 'sPendiente';
                ?>
                <Row ss:Height="18">
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['caso']); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $tipo_style; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['tipo']); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['tecnico'] ?: 'Sin asignar'); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['factura'] ?: '—'); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['cliente']); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($fecha_srv); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($fecha_fac); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['lugar'] ?: '—'); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['servicio']); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="sNumber">
                        <Data ss:Type="Number"><?php echo intval($r['cantidad'] ?? 1); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['vendedor'] ?: '—'); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['estado']); ?></Data>
                    </Cell>
                    <Cell ss:StyleID="<?php echo $alt; ?>">
                        <Data ss:Type="String"><?php echo xesc($r['notas'] ?: '—'); ?></Data>
                    </Cell>
                </Row>
            <?php endforeach; ?>

            <!-- ===== SUMMARY ROW ===== -->
            <Row ss:Height="22">
                <Cell ss:StyleID="sSummary" ss:MergeAcross="6">
                    <Data ss:Type="String">Total de comisiones exportadas: <?php echo $total_rows; ?></Data>
                </Cell>
                <Cell ss:StyleID="sSummary">
                    <Data ss:Type="Number"><?php echo array_sum(array_column($rows, 'cantidad')); ?></Data>
                </Cell>
                <Cell ss:StyleID="sSummary" ss:MergeAcross="<?php echo count($columns) - 9; ?>">
                    <Data ss:Type="String"></Data>
                </Cell>
            </Row>

        </Table>

        <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
            <PageSetup>
                <Header x:Data="&amp;L&amp;B System Taller — Comisiones RRHH&amp;R&amp;D" />
                <Footer x:Data="&amp;LExportado: <?php echo $exported_on; ?>&amp;RPágina &amp;P de &amp;N" />
                <Layout x:Orientation="Landscape" />
                <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75" />
            </PageSetup>
            <Print>
                <FitWidth>1</FitWidth>
                <ValidPrinterInfo />
                <PaperSizeIndex>9</PaperSizeIndex>
                <HorizontalResolution>600</HorizontalResolution>
                <VerticalResolution>600</VerticalResolution>
            </Print>
            <FreezePanes />
            <FrozenNoSplit />
            <SplitHorizontal>5</SplitHorizontal>
            <TopRowBottomPane>5</TopRowBottomPane>
            <ActivePane>2</ActivePane>
            <Panes>
                <Pane>
                    <Number>2</Number>
                </Pane>
            </Panes>
        </WorksheetOptions>
    </Worksheet>
</Workbook>