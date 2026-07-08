<?php

namespace Database\Seeders;

use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FAQSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $workspace = Workspace::first();

        if (! $workspace) {
            return;
        }

        $categories = FAQCategory::where('workspace_id', $workspace->id)->get()->keyBy('slug');

        $faqs = [
            // Getting Started
            [
                'category_slug' => 'getting-started',
                'question' => 'How do I create an account?',
                'answer' => 'Click the "Sign Up" button on the homepage, enter your email address and create a password. You will receive a verification email. Click the verification link to activate your account and get started.',
                'priority' => 100,
            ],
            [
                'category_slug' => 'getting-started',
                'question' => 'What do I do after logging in for the first time?',
                'answer' => 'After your first login, we recommend completing your profile, setting up your workspace, and exploring the onboarding tutorial. Check the "Getting Started" guide in the help section for a step-by-step walkthrough.',
                'priority' => 90,
            ],
            [
                'category_slug' => 'getting-started',
                'question' => 'How do I set up my workspace?',
                'answer' => 'Navigate to Workspace Settings from the sidebar. You can configure your workspace name, upload a logo, set timezone preferences, and invite team members. All changes are saved automatically.',
                'priority' => 80,
            ],

            // Account & Billing
            [
                'category_slug' => 'account-billing',
                'question' => 'How do I update my payment method?',
                'answer' => 'Go to Settings > Billing > Payment Methods. You can add a new credit card, PayPal account, or other supported payment methods. You can also set a default payment method for recurring charges.',
                'priority' => 100,
            ],
            [
                'category_slug' => 'account-billing',
                'question' => 'How do I view my invoices?',
                'answer' => 'Invoices are available under Settings > Billing > Invoice History. You can download individual invoices as PDF or request them via email. Invoices are generated at the start of each billing cycle.',
                'priority' => 80,
            ],
            [
                'category_slug' => 'account-billing',
                'question' => 'Can I change my plan?',
                'answer' => 'Yes, you can upgrade or downgrade your plan at any time from Settings > Billing > Plan. Upgrades take effect immediately with prorated charges. Downgrades apply at the end of your current billing period.',
                'priority' => 90,
            ],

            // Troubleshooting
            [
                'category_slug' => 'troubleshooting',
                'question' => 'Why is my chatbot not responding?',
                'answer' => 'Check your internet connection and ensure the chatbot service is active in your workspace settings. If the issue persists, try refreshing the page or clearing your browser cache. Contact support if the problem continues.',
                'priority' => 100,
            ],
            [
                'category_slug' => 'troubleshooting',
                'question' => 'What should I do if I encounter an error?',
                'answer' => 'Take a screenshot of the error message and note any steps that led to it. Try refreshing the page first. If the error persists, contact our support team with the screenshot and a description of what you were doing.',
                'priority' => 90,
            ],
            [
                'category_slug' => 'troubleshooting',
                'question' => 'Why are my messages not being delivered?',
                'answer' => 'Message delivery issues can occur due to network problems, recipient account restrictions, or platform rate limits. Verify the recipient account is active and not blocking messages. Check the message logs for specific error codes.',
                'priority' => 85,
            ],

            // Features & Integrations
            [
                'category_slug' => 'features-integrations',
                'question' => 'How do I connect WhatsApp?',
                'answer' => 'Go to Integrations > WhatsApp and click "Connect". You will need to scan the QR code with your WhatsApp app. Once connected, you can send and receive messages directly through our platform.',
                'priority' => 100,
            ],
            [
                'category_slug' => 'features-integrations',
                'question' => 'How do I connect Telegram?',
                'answer' => 'Navigate to Integrations > Telegram and click "Connect". You will be redirected to Telegram to authorize the connection. After authorization, your Telegram bot will be linked to your workspace.',
                'priority' => 90,
            ],
            [
                'category_slug' => 'features-integrations',
                'question' => 'Can I use multiple channels simultaneously?',
                'answer' => 'Yes, our platform supports multi-channel communication. You can connect WhatsApp, Telegram, Facebook Messenger, Instagram, and your website chatbot simultaneously. All conversations are unified in a single inbox.',
                'priority' => 95,
            ],

            // Security & Privacy
            [
                'category_slug' => 'security-privacy',
                'question' => 'How is my data encrypted?',
                'answer' => 'We use AES-256 encryption for data at rest and TLS 1.3 for data in transit. All sensitive information is encrypted before being stored in our databases. Encryption keys are managed using industry-standard key management practices.',
                'priority' => 100,
            ],
            [
                'category_slug' => 'security-privacy',
                'question' => 'Does the platform comply with GDPR?',
                'answer' => 'Yes, our platform is fully GDPR compliant. We provide data processing agreements, data subject access request tools, and the ability to delete user data upon request. Contact our privacy team for more information.',
                'priority' => 95,
            ],
            [
                'category_slug' => 'security-privacy',
                'question' => 'How do I enable two-factor authentication?',
                'answer' => 'Enable two-factor authentication (2FA) in Settings > Security > Two-Factor Authentication. You can use an authenticator app like Google Authenticator or Authy. Once enabled, you will be prompted for a code during login.',
                'priority' => 90,
            ],

            // API Reference
            [
                'category_slug' => 'api-reference',
                'question' => 'How do I get my API key?',
                'answer' => 'API keys can be generated from Settings > API > Manage Keys. Click "Generate New Key", give it a descriptive name, and select the appropriate permissions. Store the key securely as it will only be shown once.',
                'priority' => 100,
            ],
            [
                'category_slug' => 'api-reference',
                'question' => 'What are the API rate limits?',
                'answer' => 'API rate limits vary by plan. The free plan allows 100 requests per minute, while paid plans have higher limits. Rate limit headers are included in all API responses so you can monitor your usage.',
                'priority' => 90,
            ],
            [
                'category_slug' => 'api-reference',
                'question' => 'How do I authenticate API requests?',
                'answer' => 'API requests require a Bearer token in the Authorization header. Use your API key as the token value: "Authorization: Bearer YOUR_API_KEY". All API requests must be made over HTTPS.',
                'priority' => 95,
            ],

            // Best Practices
            [
                'category_slug' => 'best-practices',
                'question' => 'How often should I update my FAQs?',
                'answer' => 'We recommend reviewing and updating your FAQs monthly or whenever there are significant product or policy changes. Regular updates ensure your customers always have access to accurate and helpful information.',
                'priority' => 80,
            ],
            [
                'category_slug' => 'best-practices',
                'question' => 'How can I improve chatbot response accuracy?',
                'answer' => 'Keep FAQ answers concise and use clear language. Add relevant keywords in questions to improve matching. Regularly review unanswered queries and create new FAQs for common questions that are not covered.',
                'priority' => 90,
            ],
            [
                'category_slug' => 'best-practices',
                'question' => 'What makes a good FAQ answer?',
                'answer' => 'Good FAQ answers are concise, actionable, and directly address the question. Use step-by-step instructions when applicable, include relevant links, and avoid jargon. Aim for answers that resolve the query without requiring follow-up.',
                'priority' => 85,
            ],

            // Pricing & Plans
            [
                'category_slug' => 'pricing-plans',
                'question' => 'What plans are available?',
                'answer' => 'We offer Free, Pro, and Enterprise plans. The Free plan includes basic features with limited usage. Pro unlocks advanced features and higher limits. Enterprise offers custom solutions with dedicated support.',
                'priority' => 100,
            ],
            [
                'category_slug' => 'pricing-plans',
                'question' => 'Is there a free trial?',
                'answer' => 'Yes, we offer a 14-day free trial of our Pro plan with no credit card required. You get full access to all Pro features during the trial period. Your account automatically converts to the Free plan when the trial ends.',
                'priority' => 95,
            ],
            [
                'category_slug' => 'pricing-plans',
                'question' => 'Do you offer discounts for non-profits?',
                'answer' => 'Yes, we offer a 50% discount for verified non-profit organizations. Contact our sales team with your non-profit verification documents to apply. Educational institutions may also qualify for special pricing.',
                'priority' => 85,
            ],
        ];

        foreach ($faqs as $data) {
            $categorySlug = $data['category_slug'];
            unset($data['category_slug']);

            $data['workspace_id'] = $workspace->id;
            $data['category_id'] = $categories->has($categorySlug)
                ? $categories[$categorySlug]->id
                : null;
            $data['is_active'] = true;
            $data['hit_count'] = fake()->numberBetween(0, 5000);
            $data['last_used_at'] = fake()->optional(0.8)->dateTimeBetween('-6 months', 'now');
            $data['searchable_text'] = strip_tags($data['question'].' '.$data['answer']);

            FAQ::create($data);
        }
    }
}
