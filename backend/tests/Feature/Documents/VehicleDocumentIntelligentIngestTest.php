<?php

namespace Tests\Feature\Documents;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تنفيذ فعلي لمسار: معاينة مستند مركبة ثم تأكيد الأرشفة عبر POST /api/v1/ocr/vehicle-document
 */
class VehicleDocumentIntelligentIngestTest extends TestCase
{
    public function test_preview_then_confirm_archives_file_and_vehicle_document_row(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch, 'owner');
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Doc Test Customer',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'DOC 1234',
            'make' => 'Test',
            'model' => 'Van',
            'year' => 2024,
        ]);

        Storage::fake('public');

        $jpg = UploadedFile::fake()->create('insurance_scan.jpg', 32, 'image/jpeg');

        $preview = $this->actingAs($user, 'sanctum')->post('/api/v1/governance/ocr/vehicle-document', [
            'vehicle_id' => $vehicle->id,
            'file' => $jpg,
        ]);

        $preview->assertOk();
        $preview->assertJsonPath('preview', true);
        $preview->assertJsonStructure(['classification', 'message']);
        $this->assertDatabaseCount('vehicle_documents', 0);

        $jpg2 = UploadedFile::fake()->create('insurance_scan.jpg', 32, 'image/jpeg');

        $confirm = $this->actingAs($user, 'sanctum')->post('/api/v1/governance/ocr/vehicle-document', [
            'vehicle_id' => $vehicle->id,
            'file' => $jpg2,
            'confirm' => true,
            'document_type' => 'insurance',
            'title' => 'وثيقة تأمين — اختبار تنفيذ',
            'expiry_date' => '2027-12-31',
        ]);

        $confirm->assertStatus(201);
        $confirm->assertJsonPath('preview', false);

        $path = $confirm->json('data.file_path');
        $this->assertIsString($path);
        $this->assertNotSame('', $path);
        Storage::disk('public')->assertExists($path);

        $this->assertSame(1, VehicleDocument::where('vehicle_id', $vehicle->id)->where('company_id', $company->id)->count());
        $this->assertDatabaseHas('vehicle_documents', [
            'vehicle_id' => $vehicle->id,
            'company_id' => $company->id,
            'document_type' => 'insurance',
            'uploaded_by' => $user->id,
        ]);
    }
}
