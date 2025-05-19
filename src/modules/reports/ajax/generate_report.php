<?php
ini_set('display_errors', 0);
error_reporting(0);
if (ob_get_length()) ob_end_clean();
session_start();
require_once '../../../config/db.php';
require_once '../../../includes/permissions.php';
require_once __DIR__ . '/../../../../vendor/autoload.php'; // For TCPDF and PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

requirePermission('view_reports');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $report_type = $_POST['report_type'] ?? '';
        $format = $_POST['format'] ?? 'pdf';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        if (empty($report_type)) {
            throw new Exception("Report type is required");
        }

        // Get report data based on type
        $data = getReportData($report_type, $start_date, $end_date);
        
        // Check if data is empty and return an error if so
        if (empty($data)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No data found for the selected criteria.']);
            exit();
        }

        // Handle JSON format for chart data
        if ($format === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $data]);
            exit();
        }

        if ($format === 'excel') {
            file_put_contents(__DIR__ . '/excel_debug.txt', print_r($data, true));
            generateExcel($data, $report_type);
        } else {
            generatePDF($data, $report_type);
        }
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

function getReportData($type, $start_date, $end_date) {
    global $pdo;
    
    switch ($type) {
        case 'sales_daily':
            $sql = "SELECT 
                    DATE(s.created_at) as date,
                    COUNT(*) as total_sales,
                    SUM(s.total_amount) as total_amount
                    FROM sales s
                    WHERE s.created_at BETWEEN ? AND ?
                    GROUP BY DATE(s.created_at)
                    ORDER BY date DESC";
            break;
            
        case 'sales_monthly':
            $sql = "SELECT 
                    DATE_FORMAT(s.created_at, '%Y-%m') as month,
                    COUNT(*) as total_sales,
                    SUM(s.total_amount) as total_amount
                    FROM sales s
                    WHERE s.created_at BETWEEN ? AND ?
                    GROUP BY month
                    ORDER BY month DESC";
            break;
            
        case 'inventory':
            $sql = "SELECT 
                    p.name,
                    p.sku,
                    c.name as category,
                    p.in_stock_quantity as current_stock,
                    p.reorder_level
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.category_id
                    ORDER BY p.name";
            break;
            
        case 'attendance':
            $sql = "SELECT 
                    e.temp_name as employee_name,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days
                    FROM attendance a
                    JOIN employee_details e ON a.employee_id = e.employee_id
                    WHERE a.date BETWEEN ? AND ?
                    GROUP BY e.employee_id, e.temp_name
                    ORDER BY e.temp_name";
            break;
            
        case 'stock_movement':
            $sql = "SELECT 
                    sm.created_at as date,
                    p.name as product,
                    u.full_name as user,
                    sm.quantity,
                    sm.type,
                    sm.reference_id,
                    sm.notes
                    FROM stock_movements sm
                    LEFT JOIN products p ON sm.product_id = p.product_id
                    LEFT JOIN users u ON sm.user_id = u.user_id
                    WHERE sm.created_at BETWEEN ? AND ?
                    ORDER BY sm.created_at DESC";
            break;
            
        case 'purchase_orders':
            $sql = "SELECT 
                    po.po_number,
                    po.status,
                    po.total_amount,
                    po.created_at,
                    u.full_name as created_by,
                    po.approved_by,
                    po.received_by,
                    po.received_at,
                    po.completed_at
                    FROM purchase_orders po
                    LEFT JOIN users u ON po.created_by = u.user_id
                    WHERE po.created_at BETWEEN ? AND ?
                    ORDER BY po.created_at DESC";
            break;
            
        case 'employees':
            $sql = "SELECT 
                    e.temp_name as employee_name,
                    e.department,
                    e.position,
                    e.hire_date,
                    u.full_name as user_name,
                    e.created_at
                    FROM employee_details e
                    LEFT JOIN users u ON e.user_id = u.user_id
                    ORDER BY e.temp_name";
            break;
            
        case 'categories':
            $sql = "SELECT 
                    c.name,
                    c.description,
                    u.full_name as created_by,
                    c.created_at
                    FROM categories c
                    LEFT JOIN users u ON c.created_by = u.user_id
                    ORDER BY c.name";
            break;
            
        case 'product_performance':
            $sql = "SELECT 
                    p.name as product_name,
                    p.sku,
                    COUNT(si.sale_id) as total_sales,
                    SUM(si.quantity) as total_quantity_sold,
                    SUM(si.quantity * si.unit_price) as total_revenue,
                    p.in_stock_quantity as current_stock
                    FROM products p
                    LEFT JOIN sale_items si ON p.product_id = si.product_id
                    LEFT JOIN sales s ON si.sale_id = s.sale_id
                    WHERE s.created_at BETWEEN ? AND ?
                    GROUP BY p.product_id, p.name, p.sku, p.in_stock_quantity
                    ORDER BY total_revenue DESC";
            break;
            
        default:
            throw new Exception("Invalid report type");
    }
    
    if (in_array($type, ['inventory', 'employees', 'categories'])) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$start_date, $end_date]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateExcel($data, $report_type) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers based on report type
    switch ($report_type) {
        case 'sales_daily':
            $headers = ['Date', 'Total Sales', 'Total Amount'];
            $columnWidths = [15, 15, 20];
            break;
        case 'sales_monthly':
            $headers = ['Month', 'Total Sales', 'Total Amount'];
            $columnWidths = [15, 15, 20];
            break;
        case 'inventory':
            $headers = ['Product Name', 'SKU', 'Category', 'Current Stock', 'Reorder Level'];
            $columnWidths = [30, 15, 20, 15, 15];
            break;
        case 'attendance':
            $headers = ['Employee Name', 'Present Days', 'Absent Days', 'Late Days'];
            $columnWidths = [30, 15, 15, 15];
            break;
        case 'stock_movement':
            $headers = ['Date', 'Product', 'User', 'Quantity', 'Type', 'Reference ID', 'Notes'];
            $columnWidths = [20, 25, 25, 10, 15, 15, 30];
            break;
        case 'purchase_orders':
            $headers = ['PO Number', 'Status', 'Total Amount', 'Created At', 'Created By', 'Approved By', 'Received By', 'Received At', 'Completed At'];
            $columnWidths = [20, 15, 15, 20, 20, 20, 20, 20, 20];
            break;
        case 'employees':
            $headers = ['Employee Name', 'Department', 'Position', 'Hire Date', 'User Name', 'Created At'];
            $columnWidths = [25, 20, 20, 15, 20, 20];
            break;
        case 'categories':
            $headers = ['Category Name', 'Description', 'Created By', 'Created At'];
            $columnWidths = [20, 30, 20, 20];
            break;
    }
    
    // Set column widths
    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimensionByColumn($col + 1)->setWidth($width);
    }
    
    // Style for headers
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4F46E5'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
        ],
    ];
    
    // Add headers
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }
    
    // Apply header style
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);
    
    // Add data
    $row = 2;
    foreach ($data as $item) {
        $col = 1;
        foreach ($item as $value) {
            // Format the value based on the column
            if (strpos($headers[$col - 1], 'Amount') !== false) {
                $value = (is_numeric($value) && $value !== null && $value !== '') ? number_format($value, 2) : '';
                $sheet->getStyleByColumnAndRow($col, $row)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            } elseif ((strpos($headers[$col - 1], 'Date') !== false || strpos($headers[$col - 1], 'Month') !== false)) {
                if (!empty($value) && strtotime($value)) {
                    $value = date('Y-m-d', strtotime($value));
                } else {
                    $value = '';
                }
            } elseif ($value === null) {
                $value = '';
            }
            $sheet->setCellValueByColumnAndRow($col, $row, (string)$value);
            $col++;
        }
        $row++;
    }
    
    // Style for data cells
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            ],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        ],
    ];
    
    // Apply data style
    $lastRow = count($data) + 1;
    $sheet->getStyle('A2:' . $sheet->getHighestColumn() . $lastRow)->applyFromArray($dataStyle);
    
    // Auto-size rows
    foreach (range(1, $lastRow) as $row) {
        $sheet->getRowDimension($row)->setRowHeight(20);
    }
    
    // Set headers for download
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $report_type . '_report.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

function generatePDF($data, $report_type) {
    try {
        // Use landscape for wide tables
        $landscapeReports = ['purchase_orders', 'stock_movement'];
        $orientation = in_array($report_type, $landscapeReports) ? 'L' : 'P';
        $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('NexInvent');
        $pdf->SetTitle(ucwords(str_replace('_', ' ', $report_type)) . ' Report');
        
        // Set margins (increase left/right)
        $pdf->SetMargins(20, 40, 20); // Top margin increased for logo
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Add a page
        $pdf->AddPage();
        
        // Add logo and company name centered
        $logoPath = realpath(__DIR__ . '/../../../assets/LOGO.png');
        if (!$logoPath || !file_exists($logoPath)) {
            error_log('Logo not found at: ' . ($logoPath ?: 'NULL'));
        } else {
            $logoWidth = 50;
            $logoHeight = 18;
            $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
            $centerX = $pdf->getMargins()['left'] + ($pageWidth - $logoWidth) / 2;
            $pdf->Image($logoPath, $centerX, 12, $logoWidth, $logoHeight, 'PNG');
        }
        
        // Add header content
        $pdf->SetY(32);
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->Cell(0, 10, 'NexInvent', 0, 1, 'C');
        
        // Horizontal line
        $pdf->SetLineWidth(0.5);
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX() + $pageWidth, $pdf->GetY());
        $pdf->Ln(2);
        
        // Add date and generated by
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Date: ' . date('Y-m-d H:i'), 0, 1, 'C');
        $userName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : '');
        $pdf->Cell(0, 6, 'Generated by: ' . $userName, 0, 1, 'C');
        $pdf->Ln(5);
        
        // Add title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 12, ucwords(str_replace('_', ' ', $report_type)) . ' Report', 0, 1, 'C');
        $pdf->Ln(3);
        
        // Generate and add chart based on report type
        $chartData = generateChartData($data, $report_type);
        if ($chartData) {
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'Chart Analysis', 0, 1, 'C');
            $pdf->Ln(2);
            
            // Create chart image
            $chartImage = createChartImage($chartData, $report_type);
            if ($chartImage) {
                $pdf->Image($chartImage, null, null, 180, 100, 'PNG');
                $pdf->Ln(110); // Space after chart
                
                // Clean up temporary image file
                @unlink($chartImage);
            } else {
                // Add a message if chart generation failed
                $pdf->SetFont('helvetica', 'I', 10);
                $pdf->Cell(0, 10, 'Chart generation is not available. Please install wkhtmltoimage for chart support.', 0, 1, 'C');
                $pdf->Ln(5);
            }
        }
        
        // Continue with table generation...
        // Set font back to normal
        $pdf->SetFont('helvetica', '', 10);
        // Add data
        $pdf->SetFont('helvetica', 'B', 10);
        // Define column widths based on report type
        switch ($report_type) {
            case 'sales_daily':
                $widths = array(50, 50, 50);
                $headers = array('Date', 'Total Sales', 'Total Amount');
                break;
            case 'sales_monthly':
                $widths = array(50, 50, 50);
                $headers = array('Month', 'Total Sales', 'Total Amount');
                break;
            case 'inventory':
                $widths = array(60, 30, 30, 30, 30);
                $headers = array('Product Name', 'SKU', 'Category', 'Stock', 'Reorder Level');
                break;
            case 'attendance':
                $widths = array(60, 40, 40, 40);
                $headers = array('Employee Name', 'Present Days', 'Absent Days', 'Late Days');
                break;
            case 'stock_movement':
                $widths = array(30, 35, 35, 15, 20, 20, 40);
                $headers = array('Date', 'Product', 'User', 'Quantity', 'Type', 'Reference ID', 'Notes');
                break;
            case 'purchase_orders':
                $widths = array(25, 20, 25, 25, 25, 25, 25, 25, 25);
                $headers = array('PO Number', 'Status', 'Total Amount', 'Created At', 'Created By', 'Approved By', 'Received By', 'Received At', 'Completed At');
                break;
            case 'employees':
                $widths = array(35, 25, 25, 20, 25, 25);
                $headers = array('Employee Name', 'Department', 'Position', 'Hire Date', 'User Name', 'Created At');
                break;
            case 'categories':
                $widths = array(30, 40, 30, 30);
                $headers = array('Category Name', 'Description', 'Created By', 'Created At');
                break;
            case 'product_performance':
                $widths = array(40, 25, 25, 25, 30, 25);
                $headers = array('Product Name', 'SKU', 'Total Sales', 'Quantity Sold', 'Total Revenue', 'Current Stock');
                break;
        }
        // Table header styling
        $pdf->SetFillColor(79, 70, 229); // purple
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 10);
        foreach ($headers as $i => $header) {
            $pdf->MultiCell($widths[$i], 8, $header, 1, 'C', 1, 0, '', '', true, 0, false, true, 8, 'M');
        }
        $pdf->Ln();
        // Table body styling
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        foreach ($data as $row) {
            $i = 0;
            foreach ($row as $value) {
                // Format the value based on the column
                if (strpos($headers[$i], 'Amount') !== false) {
                    $value = number_format($value, 2);
                } elseif (strpos($headers[$i], 'Date') !== false || strpos($headers[$i], 'Month') !== false) {
                    $value = !empty($value) && strtotime($value) ? date('Y-m-d', strtotime($value)) : '';
                }
                $pdf->MultiCell($widths[$i], 8, (string)$value, 1, 'L', 0, 0, '', '', true, 0, false, true, 8, 'M');
                $i++;
            }
            $pdf->Ln();
        }
        // Output PDF
        if (ob_get_length()) ob_end_clean();
        $pdf->Output($report_type . '_report.pdf', 'D');
        exit();
    } catch (Throwable $e) {
        error_log('PDF generation error: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'PDF generation failed: ' . $e->getMessage()]);
        exit();
    }
}

function generateChartData($data, $report_type) {
    switch ($report_type) {
        case 'sales_daily':
            return [
                'labels' => array_column($data, 'date'),
                'datasets' => [
                    [
                        'label' => 'Total Sales',
                        'data' => array_column($data, 'total_sales')
                    ],
                    [
                        'label' => 'Total Amount',
                        'data' => array_column($data, 'total_amount')
                    ]
                ]
            ];
            
        case 'product_performance':
            // Get top 10 products by revenue
            $topProducts = array_slice($data, 0, 10);
            return [
                'labels' => array_column($topProducts, 'product_name'),
                'datasets' => [
                    [
                        'label' => 'Total Revenue',
                        'data' => array_column($topProducts, 'total_revenue')
                    ]
                ]
            ];
            
        case 'inventory':
            // Get products below reorder level
            $lowStock = array_filter($data, function($item) {
                return $item['current_stock'] <= $item['reorder_level'];
            });
            return [
                'labels' => array_column($lowStock, 'name'),
                'datasets' => [
                    [
                        'label' => 'Current Stock',
                        'data' => array_column($lowStock, 'current_stock')
                    ],
                    [
                        'label' => 'Reorder Level',
                        'data' => array_column($lowStock, 'reorder_level')
                    ]
                ]
            ];
            
        default:
            return null;
    }
}

function isWkhtmltoimageInstalled() {
    // Common installation paths
    $possiblePaths = [
        'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltoimage.exe',
        'C:\\Program Files (x86)\\wkhtmltopdf\\bin\\wkhtmltoimage.exe',
        'wkhtmltoimage' // This will work if it's in PATH
    ];
    
    foreach ($possiblePaths as $path) {
        // Try to get the version
        $command = $path . ' --version';
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && !empty($output)) {
            return true;
        }
    }
    
    // If we get here, wkhtmltoimage is not found
    error_log('wkhtmltoimage not found in common paths or PATH');
    return false;
}

function createChartImage($chartData, $report_type) {
    // Check if wkhtmltoimage is installed
    if (!isWkhtmltoimageInstalled()) {
        error_log('wkhtmltoimage is not installed. Charts will not be generated.');
        return null;
    }
    
    // Create a temporary file for the chart
    $tempFile = tempnam(sys_get_temp_dir(), 'chart_');
    
    // Use Chart.js to generate the chart
    $html = '<html><head><script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head><body>';
    $html .= '<canvas id="chart" width="800" height="400"></canvas>';
    $html .= '<script>';
    $html .= 'const ctx = document.getElementById("chart").getContext("2d");';
    $html .= 'new Chart(ctx, {';
    $html .= 'type: "' . ($report_type === 'inventory' ? 'bar' : 'line') . '",';
    $html .= 'data: ' . json_encode($chartData) . ',';
    $html .= 'options: {';
    $html .= 'responsive: true,';
    $html .= 'maintainAspectRatio: false,';
    $html .= 'plugins: {';
    $html .= 'legend: { position: "top" },';
    $html .= 'title: { display: true, text: "' . ucwords(str_replace('_', ' ', $report_type)) . ' Analysis" }';
    $html .= '}';
    $html .= '}';
    $html .= '});';
    $html .= '</script></body></html>';
    
    // Save the HTML to a temporary file
    file_put_contents($tempFile . '.html', $html);
    
    // Try different wkhtmltoimage paths
    $wkhtmltoimagePaths = [
        'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltoimage.exe',
        'C:\\Program Files (x86)\\wkhtmltopdf\\bin\\wkhtmltoimage.exe',
        'wkhtmltoimage'
    ];
    
    $success = false;
    foreach ($wkhtmltoimagePaths as $path) {
        $command = $path . ' --width 800 --height 400 ' . escapeshellarg($tempFile . '.html') . ' ' . escapeshellarg($tempFile . '.png');
        exec($command, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($tempFile . '.png')) {
            $success = true;
            break;
        }
    }
    
    // Clean up temporary HTML file
    @unlink($tempFile . '.html');
    
    if (!$success) {
        error_log('Failed to generate chart image. Tried all possible wkhtmltoimage paths.');
        return null;
    }
    
    return $tempFile . '.png';
} 