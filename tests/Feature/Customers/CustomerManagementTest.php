<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    private function sales(array $extra = []): User
    {
        return $this->userWith(array_merge(['customers.view', 'customers.create', 'customers.update'], $extra));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Nusrat Jahan',
            'primary_mobile' => '+880 1765-432109',
            'status' => 'active',
            'source' => 'facebook',
        ], $overrides);
    }

    public function test_create_customer_with_code_normalised_mobile_and_address(): void
    {
        $user = $this->sales();

        $this->actingAs($user)->ajax()->post(route('customers.store'), $this->payload(['address_line' => 'House 1', 'city' => 'Uttara']))->assertOk();

        $customer = Customer::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^CUS-\d{5}$/', $customer->customer_code);
        $this->assertSame('01765432109', $customer->primary_mobile);
        $this->assertTrue($customer->addresses()->firstOrFail()->is_primary);
    }

    public function test_primary_mobile_is_unique_across_formats(): void
    {
        $user = $this->sales();
        $this->actingAs($user)->ajax()->post(route('customers.store'), $this->payload())->assertOk();

        $this->actingAs($user)->ajax()->post(route('customers.store'), $this->payload(['full_name' => 'Other', 'primary_mobile' => '01765432109']))
            ->assertStatus(422)
            ->assertJsonPath('errors.primary_mobile.0', 'Already registered to Nusrat Jahan ('.Customer::first()->customer_code.').');
    }

    public function test_duplicate_message_does_not_leak_customer_the_user_cannot_see(): void
    {
        Customer::factory()->create(['full_name' => 'Secret Person', 'primary_mobile' => '01700000001']);

        $response = $this->actingAs($this->sales())->ajax()->post(route('customers.store'), $this->payload(['primary_mobile' => '01700000001']))
            ->assertStatus(422);

        $this->assertStringNotContainsString('Secret Person', $response->getContent());
    }

    public function test_alternate_number_match_warns_without_blocking(): void
    {
        $user = $this->sales();
        Customer::factory()->create(['full_name' => 'Kamal', 'primary_mobile' => '01811111111', 'alternate_mobile' => '01922222222', 'created_by' => $user->id]);

        $message = $this->actingAs($user)->ajax()->post(route('customers.store'), $this->payload(['primary_mobile' => '01922222222']))
            ->assertOk()->json('message');

        $this->assertStringContainsString('Possible duplicate: Kamal', $message);
    }

    public function test_sales_user_sees_only_own_customers(): void
    {
        $user = $this->sales();
        $mine = Customer::factory()->create(['full_name' => 'Mine One', 'created_by' => $user->id]);
        $other = Customer::factory()->create(['full_name' => 'Not Mine']);

        $html = $this->actingAs($user)->ajax()->get(route('customers.table'))->json('data.html');
        $this->assertStringContainsString('Mine One', $html);
        $this->assertStringNotContainsString('Not Mine', $html);

        $this->actingAs($user)->get(route('customers.show', $mine))->assertOk();
        $this->actingAs($user)->get(route('customers.show', $other))->assertForbidden();
        $this->actingAs($user)->ajax()->get(route('customers.edit', $other))->assertForbidden();
        $this->actingAs($user)->ajax()->get(route('customers.notes.index', $other))->assertForbidden();
        $this->actingAs($user)->ajax()->get(route('customers.activity', $other))->assertForbidden();
        $this->actingAs($user)->ajax()->get(route('options.customers'))->assertJsonCount(1, 'results');

        $this->actingAs($this->userWith(['customers.view_all']))->ajax()->get(route('customers.table'))->assertJsonPath('meta.total', 2);
    }

    public function test_identity_number_is_encrypted_masked_and_never_logged(): void
    {
        $user = $this->sales(['customers.view_sensitive']);
        $this->actingAs($user)->ajax()->post(route('customers.store'), $this->payload(['nid_or_passport' => '1987 2345 6789']))->assertOk();
        $customer = Customer::query()->firstOrFail();

        $raw = DB::table('customers')->where('id', $customer->id)->value('nid_or_passport');
        $this->assertStringNotContainsString('198723456789', $raw);
        $this->assertSame('••••••••6789', $customer->maskedIdentity());
        $this->assertStringNotContainsString('198723456789', DB::table('activity_log')->pluck('properties')->implode(' '));

        $this->actingAs($user)->ajax()->post(route('customers.identity', $customer))->assertOk()->assertJsonPath('data.value', '198723456789');
        $this->assertDatabaseHas('activity_log', ['event' => 'identity_viewed', 'subject_id' => $customer->id]);
    }

    public function test_identity_hidden_without_sensitive_permission(): void
    {
        $user = $this->sales();
        $customer = Customer::factory()->create(['created_by' => $user->id, 'nid_or_passport' => 'AB1234567']);

        $this->actingAs($user)->get(route('customers.show', $customer))->assertOk()->assertDontSee('AB1234567')->assertDontSee('4567');
        $this->actingAs($user)->ajax()->post(route('customers.identity', $customer))->assertForbidden();

        // Editing without the permission leaves the stored value untouched.
        $this->actingAs($user)->ajax()->put(route('customers.update', $customer), $this->payload([
            'full_name' => 'Changed', 'primary_mobile' => $customer->primary_mobile, 'nid_or_passport' => 'ZZ999',
        ]))->assertOk();
        $this->assertSame('AB1234567', $customer->fresh()->nid_or_passport);
    }

    public function test_documents_are_private_and_need_permission(): void
    {
        Storage::fake('local');
        $user = $this->sales(['customers.manage_documents']);
        $customer = Customer::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->ajax()->post(route('customers.documents.store', $customer), [
            'category' => 'nid', 'file' => UploadedFile::fake()->create('nid.pdf', 200, 'application/pdf'),
        ])->assertOk();
        $document = $customer->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith('customers/'.$customer->public_id.'/', $document->path);

        $this->actingAs($user)->get(route('customers.documents.download', [$customer, $document]))->assertOk()->assertDownload('nid.pdf');

        $this->actingAs($user)->ajax()->post(route('customers.documents.store', $customer), [
            'category' => 'other', 'file' => UploadedFile::fake()->create('shell.php', 5, 'application/x-php'),
        ])->assertStatus(422);
        $this->actingAs($user)->ajax()->post(route('customers.documents.store', $customer), [
            'category' => 'other', 'file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'),
        ])->assertStatus(422);

        $plain = $this->sales();
        $customer->update(['created_by' => $plain->id]);
        $this->actingAs($plain)->get(route('customers.documents.download', [$customer, $document]))->assertForbidden();
        $this->actingAs($plain)->ajax()->get(route('customers.documents.index', $customer))->assertForbidden();
    }

    public function test_document_of_one_customer_cannot_be_fetched_through_another(): void
    {
        $user = $this->userWith(['customers.view_all', 'customers.manage_documents']);
        [$a, $b] = Customer::factory()->count(2)->create();
        $doc = $b->documents()->create(['category' => 'nid', 'original_name' => 'x.pdf', 'disk' => 'local', 'path' => 'x.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1, 'uploaded_by' => $user->id]);

        $this->actingAs($user)->get('/customers/'.$a->public_id.'/documents/'.$doc->public_id)->assertNotFound();
    }

    public function test_notes_can_be_deleted_by_author_only_unless_editor(): void
    {
        $viewer = $this->userWith(['customers.view_all']);
        $author = $this->userWith(['customers.view_all']);
        $customer = Customer::factory()->create();
        $note = $customer->notes()->create(['body' => 'Called', 'created_by' => $author->id]);

        $this->actingAs($viewer)->ajax()->delete(route('customers.notes.destroy', [$customer, $note]))->assertForbidden();
        $this->actingAs($author)->ajax()->delete(route('customers.notes.destroy', [$customer, $note]))->assertOk();
        $this->assertSoftDeleted($note);
    }

    public function test_future_modules_show_honest_placeholders(): void
    {
        $user = $this->sales();
        $customer = Customer::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->get(route('customers.show', $customer))->assertOk()->assertSee('Coming in Phase 3');
    }

    public function test_archive_and_restore(): void
    {
        $user = $this->sales(['customers.archive']);
        $customer = Customer::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->ajax()->delete(route('customers.destroy', $customer))->assertOk();
        $this->assertSoftDeleted($customer);
        $this->actingAs($user)->ajax()->post(route('customers.restore', $customer->public_id))->assertOk();
        $this->assertNotSoftDeleted($customer);
    }
}
