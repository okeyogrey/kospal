<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Business;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'attachable_type' => (new Expense)->getMorphClass(),
            'attachable_id' => Expense::factory(),
            'disk' => 'attachments',
            'path' => 'expenses/'.fake()->uuid().'.pdf',
            'original_name' => fake()->lexify('receipt-????.pdf'),
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 500_000),
            'uploaded_by' => User::factory(),
        ];
    }
}
