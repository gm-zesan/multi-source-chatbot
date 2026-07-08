<?php

namespace Database\Factories;

use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FAQ>
 */
class FAQFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = FAQ::class;

    /**
     * Predefined FAQ question-answer pairs for realistic seeding.
     */
    private static array $predefinedFaqs = [
        [
            'question' => 'How do I reset my password?',
            'answer' => 'To reset your password, click the "Forgot Password" link on the login page. Enter your registered email address, and we will send you a password reset link. Follow the instructions in the email to create a new password. If you do not receive the email within 5 minutes, please check your spam folder.',
        ],
        [
            'question' => 'How do I change my billing information?',
            'answer' => 'You can update your billing information by navigating to Settings > Billing in your account dashboard. From there, you can update your payment method, billing address, and view your invoice history. Changes take effect immediately for future billing cycles.',
        ],
        [
            'question' => 'Can I integrate with third-party services?',
            'answer' => 'Yes, our platform supports integrations with popular third-party services including Slack, WhatsApp, Telegram, Facebook Messenger, and Instagram. Visit the Integrations section in your workspace settings to configure and manage your connected services.',
        ],
        [
            'question' => 'What is the cancellation policy?',
            'answer' => 'You can cancel your subscription at any time from your account settings. Upon cancellation, your access will continue until the end of the current billing period. No partial refunds are provided for the remaining days in the billing cycle.',
        ],
        [
            'question' => 'How do I invite team members to my workspace?',
            'answer' => 'To invite team members, go to your workspace settings and click on "Team Members". Click the "Invite Member" button, enter their email address, and assign a role. They will receive an email invitation to join your workspace.',
        ],
        [
            'question' => 'Is my data secure?',
            'answer' => 'Yes, we take security seriously. All data is encrypted at rest and in transit using industry-standard protocols. We use AES-256 encryption for data at rest and TLS 1.3 for data in transit. Our infrastructure is hosted in SOC 2 compliant data centers.',
        ],
        [
            'question' => 'How do I export my data?',
            'answer' => 'You can export your data by going to Settings > Data Management > Export. Choose the data you wish to export and select your preferred format (CSV, JSON, or Excel). The export process will prepare your data and notify you via email when it is ready for download.',
        ],
        [
            'question' => 'What happens when I reach my usage limit?',
            'answer' => 'When you approach your usage limit, you will receive email notifications at 80%, 90%, and 100% usage. If you exceed your plan limits, non-critical features may be temporarily restricted. You can upgrade your plan at any time to avoid interruptions.',
        ],
        [
            'question' => 'How do I set up automated responses?',
            'answer' => 'Automated responses can be configured in the Automation section of your workspace. You can create rules based on keywords, message types, or schedules. Use our drag-and-drop builder to design response workflows without any coding required.',
        ],
        [
            'question' => 'Can I customize the chatbot appearance?',
            'answer' => 'Yes, you can fully customize the chatbot widget appearance including colors, fonts, logo, and messaging style. Navigate to Settings > Chat Widget to access the customization options. Changes are reflected in real-time on your website.',
        ],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $faq = fake()->randomElement(self::$predefinedFaqs);

        return [
            'workspace_id' => Workspace::factory(),
            'category_id' => null,
            'question' => $faq['question'],
            'answer' => $faq['answer'],
            'searchable_text' => null,
            'embedding_version' => null,
            'is_active' => true,
            'priority' => fake()->numberBetween(0, 100),
            'hit_count' => fake()->numberBetween(0, 5000),
            'last_used_at' => fake()->optional(0.8)->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Assign this FAQ to a specific category.
     */
    public function inCategory(FAQCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    /**
     * Mark the FAQ as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a custom question and answer.
     */
    public function withQuestion(string $question, string $answer): static
    {
        return $this->state(fn (array $attributes) => [
            'question' => $question,
            'answer' => $answer,
        ]);
    }

    /**
     * Auto-generate searchable_text from question and answer.
     */
    public function withSearchableText(): static
    {
        return $this->state(function (array $attributes) {
            $text = strip_tags($attributes['question'].' '.$attributes['answer']);

            return [
                'searchable_text' => $text,
            ];
        });
    }
}
