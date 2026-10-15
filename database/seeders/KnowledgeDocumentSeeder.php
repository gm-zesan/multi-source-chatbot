<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentLifecycleStatus;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KnowledgeDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workspace = Workspace::first() ?? Workspace::create([
            'name' => 'Default Workspace',
            'slug' => 'default-workspace',
        ]);

        $admin = User::first() ?? User::create([
            'name'         => 'System Admin',
            'email'        => 'admin@example.com',
            'password'     => bcrypt('password'),
            'workspace_id' => $workspace->id,
        ]);

        $documents = [
            [
                'title'             => 'Return & Refund Policy 2026',
                'original_filename' => 'return_and_refund_policy_2026.txt',
                'extension'         => 'txt',
                'mime_type'         => 'text/plain',
                'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
                'content'           => <<<'TXT'
=====================================================
ENTREPRENEURS AUTOMATION - RETURN & REFUND POLICY 2026
=====================================================

1. RETURN ELIGIBILITY
- Customers may request a return within 7 calendar days from the date of physical parcel delivery.
- Items must remain unused, unwashed, and in the original packaging with all security tags intact.
- Proof of purchase (Order ID or physical receipt) is strictly mandatory.

2. NON-RETURNABLE PRODUCTS
- Perishable goods, intimate items, clearance/final sale merchandise, and digital license keys.

3. EXCHANGE TIMELINES
- Product exchanges are fulfilled within 3-5 business days upon receiving and inspecting the returned item.

4. REFUND METHOD & SCHEDULE
- Approved refunds are credited back to the customer's original payment channel (bKash, Nagad, or Bank Card).
- Processing duration is 5 to 7 business days following QA inspection at our central fulfillment hub.
TXT,
            ],
            [
                'title'             => 'Shipping & Delivery Guidelines',
                'original_filename' => 'shipping_and_delivery_guidelines.pdf',
                'extension'         => 'pdf',
                'mime_type'         => 'application/pdf',
                'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
                'content'           => "%PDF-1.4\n1 0 obj\n<< /Title (Shipping & Delivery Guidelines) /Subject (Standard shipping SLA: 24-48 hrs Inside Dhaka, 3-5 days Nationwide. Courier partners: Pathao, Steadfast, eCourier.) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
            ],
            [
                'title'             => 'Product Warranty & Service Terms',
                'original_filename' => 'product_warranty_terms.docx',
                'extension'         => 'docx',
                'mime_type'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
                'content'           => "PK\x03\x04[Content_Types].xml - 12 Months Official Brand Replacement Warranty on manufacturing defects. Excludes liquid exposure, surge damage, and unauthorized third-party repairs.",
            ],
            [
                'title'             => 'B2B Wholesale & Bulk Purchase Schedule',
                'original_filename' => 'wholesale_tier_schedule.csv',
                'extension'         => 'csv',
                'mime_type'         => 'text/csv',
                'lifecycle_status'  => DocumentLifecycleStatus::PENDING_INGESTION,
                'content'           => "tier_code,tier_name,minimum_units,discount_percentage,payment_terms,dispatch_lead_days\nT1,Silver Bulk,50,10%,50% Advance Balance on Delivery,3\nT2,Gold Commercial,200,18%,50% Advance Net 15,5\nT3,Enterprise Distributor,500,25%,30% Advance Net 30,7\n",
            ],
        ];

        foreach ($documents as $docData) {
            $contentHash = hash('sha256', $docData['content']);

            // Avoid re-creating duplicate content if already seeded
            $existing = KnowledgeDocument::where('workspace_id', $workspace->id)
                ->where('content_hash', $contentHash)
                ->first();

            if ($existing) {
                continue;
            }

            $uuid = (string) Str::uuid();
            $storageFilename = "{$uuid}.{$docData['extension']}";
            $relativePath = "documents/{$workspace->id}/{$storageFilename}";

            // Persist the actual document to the private storage disk
            Storage::disk('local')->put($relativePath, $docData['content']);

            KnowledgeDocument::create([
                'id'                => $uuid,
                'workspace_id'      => $workspace->id,
                'title'             => $docData['title'],
                'original_filename' => $docData['original_filename'],
                'file_path'         => $relativePath,
                'mime_type'         => $docData['mime_type'],
                'file_size'           => strlen($docData['content']),
                'content_hash'        => $contentHash,
                'active_content_hash' => $contentHash,
                'lifecycle_status'    => $docData['lifecycle_status'],
                'chunk_count'       => 0,
                'version'           => 1,
                'is_active'         => false,
                'error_message'     => null,
                'created_by'        => $admin->id,
                'updated_by'        => $admin->id,
            ]);
        }
    }
}
