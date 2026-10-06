<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class GenerateTestExcel extends Command
{
    protected $signature = 'generate:test-excel {--path=storage/app/business_test_multitab.xlsx}';
    protected $description = 'Generate a rich multi-tab Excel file for manual testing';

    public function handle(): int
    {
        $spreadsheet = new Spreadsheet();

        // ── Tab 1: Sales Transactions ────────────────────────────────
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Sales_Transactions');

        $salesHeaders = ['Date', 'Invoice_ID', 'Customer_Name', 'Salesperson', 'Product_Name', 'Category', 'Quantity', 'Unit_Price', 'Total_Amount', 'Payment_Method', 'Status'];
        $sheet1->fromArray([$salesHeaders], null, 'A1');

        $salesData = [
            ['2026-10-01', 'INV-1001', 'Rakib Hasan', 'Md. Tanvir', 'Basmati Rice 25kg', 'Grains', 10, 2600, 26000, 'cash', 'Completed'],
            ['2026-10-01', 'INV-1002', 'Karim Ullah', 'Rakib Hasan', 'Soybean Oil 5L', 'Edible Oil', 25, 820, 20500, 'bkash', 'Completed'],
            ['2026-10-02', 'INV-1003', 'Hasan Mahmud', 'Md. Tanvir', 'Miniket Rice 50kg', 'Grains', 15, 3400, 51000, 'bank', 'Completed'],
            ['2026-10-02', 'INV-1004', 'Sultana Begum', 'Fahim Ahmed', 'Sugar 50kg', 'Groceries', 8, 4800, 38400, 'cash', 'Completed'],
            ['2026-10-03', 'INV-1005', 'Arif Hossain', 'Rakib Hasan', 'Atta 2kg Pack', 'Grains', 100, 115, 11500, 'bkash', 'Completed'],
            ['2026-10-03', 'INV-1006', 'Jashim Uddin', 'Md. Tanvir', 'Mustard Oil 1L', 'Edible Oil', 40, 260, 10400, 'cash', 'Completed'],
            ['2026-10-04', 'INV-1007', 'Farhana Akter', 'Fahim Ahmed', 'Basmati Rice 25kg', 'Grains', 5, 2600, 13000, 'card', 'Completed'],
            ['2026-10-04', 'INV-1008', 'Shafiqul Islam', 'Rakib Hasan', 'Red Lentils (Moshur) 25kg', 'Pulses', 12, 3100, 37200, 'bank', 'Completed'],
            ['2026-10-05', 'INV-1009', 'Md. Nazmul', 'Md. Tanvir', 'Soybean Oil 5L', 'Edible Oil', 30, 820, 24600, 'cash', 'Completed'],
            ['2026-10-05', 'INV-1010', 'Habibur Rahman', 'Fahim Ahmed', 'Sugar 50kg', 'Groceries', 10, 4800, 48000, 'bkash', 'Completed'],
            ['2026-10-06', 'INV-1011', 'Rakib Hasan', 'Md. Tanvir', 'Miniket Rice 50kg', 'Grains', 8, 3400, 27200, 'cash', 'Completed'],
            ['2026-10-06', 'INV-1012', 'Mahmudul Haque', 'Rakib Hasan', 'Tea Leaf 500g', 'Beverages', 50, 240, 12000, 'bkash', 'Completed'],
        ];
        $sheet1->fromArray($salesData, null, 'A2');
        $this->styleHeader($sheet1, 'A1:K1', '1E40AF');

        // ── Tab 2: Cash Collections ──────────────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Cash_Collections');

        $colHeaders = ['Date', 'Collection_ID', 'Customer_Name', 'Collector_Salesperson', 'Collection_Amount', 'Payment_Method', 'Reference_Notes'];
        $sheet2->fromArray([$colHeaders], null, 'A1');

        $colData = [
            ['2026-10-01', 'COL-201', 'Rakib Hasan', 'Md. Tanvir', 26000, 'cash', 'Invoice INV-1001 full cash settlement'],
            ['2026-10-01', 'COL-202', 'Karim Ullah', 'Rakib Hasan', 20500, 'bkash', 'TrxID: 9X82K19A'],
            ['2026-10-02', 'COL-203', 'Hasan Mahmud', 'Md. Tanvir', 30000, 'bank', 'City Bank Cheque #49281'],
            ['2026-10-02', 'COL-204', 'Sultana Begum', 'Fahim Ahmed', 38400, 'cash', 'Full payment at store counter'],
            ['2026-10-03', 'COL-205', 'Arif Hossain', 'Rakib Hasan', 11500, 'bkash', 'TrxID: 7M19PL34'],
            ['2026-10-03', 'COL-206', 'Jashim Uddin', 'Md. Tanvir', 10400, 'cash', 'Cash collection from shop'],
            ['2026-10-04', 'COL-207', 'Farhana Akter', 'Fahim Ahmed', 13000, 'card', 'POS Terminal #2 slip'],
            ['2026-10-05', 'COL-208', 'Md. Nazmul', 'Md. Tanvir', 24600, 'cash', 'Direct cash received'],
            ['2026-10-06', 'COL-209', 'Rakib Hasan', 'Md. Tanvir', 27200, 'cash', 'Advance payment for Rice'],
            ['2026-10-06', 'COL-210', 'Mahmudul Haque', 'Rakib Hasan', 12000, 'bkash', 'TrxID: 8B43QP90'],
        ];
        $sheet2->fromArray($colData, null, 'A2');
        $this->styleHeader($sheet2, 'A1:G1', '047857');

        // ── Tab 3: Product Inventory ─────────────────────────────────
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Product_Inventory');

        $invHeaders = ['Product_ID', 'Product_Name', 'Category', 'Stock_Quantity', 'Unit_Cost_Price', 'Selling_Price', 'Supplier_Name', 'Reorder_Level'];
        $sheet3->fromArray([$invHeaders], null, 'A1');

        $invData = [
            ['PRD-01', 'Basmati Rice 25kg', 'Grains', 140, 2250, 2600, 'Pran Agro Ltd', 25],
            ['PRD-02', 'Miniket Rice 50kg', 'Grains', 85, 2950, 3400, 'Rashid Agro', 20],
            ['PRD-03', 'Soybean Oil 5L', 'Edible Oil', 210, 710, 820, 'City Group (Teer)', 50],
            ['PRD-04', 'Mustard Oil 1L', 'Edible Oil', 320, 215, 260, 'Square Consumer', 60],
            ['PRD-05', 'Sugar 50kg', 'Groceries', 45, 4200, 4800, 'Fresh Foods Ltd', 15],
            ['PRD-06', 'Atta 2kg Pack', 'Grains', 450, 95, 115, 'ACI Foods', 100],
            ['PRD-07', 'Red Lentils (Moshur) 25kg', 'Pulses', 60, 2650, 3100, 'Meghna Group', 20],
            ['PRD-08', 'Tea Leaf 500g', 'Beverages', 180, 190, 240, 'Ispahani Tea', 40],
        ];
        $sheet3->fromArray($invData, null, 'A2');
        $this->styleHeader($sheet3, 'A1:H1', '6D28D9');

        // ── Tab 4: Monthly Expenses ──────────────────────────────────
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Monthly_Expenses');

        $expHeaders = ['Date', 'Expense_ID', 'Expense_Category', 'Description', 'Amount', 'Paid_By', 'Payment_Method', 'Status'];
        $sheet4->fromArray([$expHeaders], null, 'A1');

        $expData = [
            ['2026-10-01', 'EXP-301', 'Rent', 'Warehouse & Store Rent for October', 45000, 'Zesan Admin', 'bank', 'Paid'],
            ['2026-10-02', 'EXP-302', 'Utilities', 'Electricity Bill (DESCO)', 8400, 'Md. Tanvir', 'bkash', 'Paid'],
            ['2026-10-02', 'EXP-303', 'Logistics', 'Truck freight delivery charge', 6500, 'Fahim Ahmed', 'cash', 'Paid'],
            ['2026-10-03', 'EXP-304', 'Salaries', 'Staff advance salary', 25000, 'Zesan Admin', 'bank', 'Paid'],
            ['2026-10-04', 'EXP-305', 'Maintenance', 'CCTV & Network repair', 3200, 'Md. Tanvir', 'cash', 'Paid'],
            ['2026-10-05', 'EXP-306', 'Packaging', 'Plastic & gunny bags purchase', 7800, 'Rakib Hasan', 'cash', 'Paid'],
            ['2026-10-06', 'EXP-307', 'Refreshments', 'Office tea and guest snacks', 1650, 'Fahim Ahmed', 'cash', 'Paid'],
        ];
        $sheet4->fromArray($expData, null, 'A2');
        $this->styleHeader($sheet4, 'A1:H1', 'B91C1C');

        // ── Tab 5: Customer Dues ─────────────────────────────────────
        $sheet5 = $spreadsheet->createSheet();
        $sheet5->setTitle('Customer_Dues');

        $dueHeaders = ['Customer_ID', 'Customer_Name', 'Phone_Number', 'Area_Location', 'Total_Purchased_Amount', 'Total_Paid_Amount', 'Current_Due_Amount'];
        $sheet5->fromArray([$dueHeaders], null, 'A1');

        $dueData = [
            ['CUST-01', 'Rakib Hasan', '01711001122', 'Dhanmondi, Dhaka', 53200, 53200, 0],
            ['CUST-02', 'Hasan Mahmud', '01819223344', 'Uttara, Dhaka', 51000, 30000, 21000],
            ['CUST-03', 'Karim Ullah', '01912334455', 'Mirpur, Dhaka', 20500, 20500, 0],
            ['CUST-04', 'Sultana Begum', '01678112233', 'Gulshan, Dhaka', 38400, 38400, 0],
            ['CUST-05', 'Shafiqul Islam', '01755667788', 'Motijheel, Dhaka', 37200, 15000, 22200],
            ['CUST-06', 'Habibur Rahman', '01533445566', 'Mohakhali, Dhaka', 48000, 25000, 23000],
        ];
        $sheet5->fromArray($dueData, null, 'A2');
        $this->styleHeader($sheet5, 'A1:G1', 'C2410C');

        // Auto-fit columns across all sheets
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $destination = base_path($this->option('path') ?: 'storage/app/business_test_multitab.xlsx');
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($destination);

        // Also copy to public directory for easy direct download via URL
        $publicDest = public_path('downloads/business_test_multitab.xlsx');
        $publicDir = dirname($publicDest);
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }
        copy($destination, $publicDest);

        $this->info("Successfully generated multi-tab Excel file at: {$destination}");
        $this->info("Public download available at: {$publicDest}");

        return self::SUCCESS;
    }

    private function styleHeader($sheet, string $range, string $hexColor): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $hexColor],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
    }
}

