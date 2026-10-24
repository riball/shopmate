<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'home' => [
                'title' => 'Find the right product, with every fact checked',
                'body' => 'Describe what you want in plain English. ShopMate compares stores, checks every statement against the listing data, and never buys anything without your approval.',
            ],
            'how-it-works' => [
                'title' => 'How ShopMate checks its answers',
                'body' => <<<'HTML'
<p>ShopMate is a fixed sequence of steps. The AI makes a few decisions inside it; plain code does everything that has to be exact.</p>
<ol>
<li><strong>Plan.</strong> The AI turns your sentence into a work order: product type, budget and must-haves. A budget you write ("under ₹2,000", "20k", "1.5 lakh") is read by code, not guessed.</li>
<li><strong>Clarify.</strong> If you don't say what kind of product you want, it asks you one question instead of guessing. It never asks twice.</li>
<li><strong>Search.</strong> Code removes anything over budget and checks each must-have against the listing's specs. Clearly failing products are removed; anything the listing doesn't state is kept but marked "couldn't confirm".</li>
<li><strong>Relax, if needed.</strong> If nothing fits, code works out each single change that would bring products back (a budget raise of up to 50%, or dropping one must-have). The AI picks one, and you see exactly what was given up.</li>
<li><strong>Rank.</strong> Ratings are weighted by review count, so 5 stars from 3 reviews doesn't beat 4.5 stars from thousands. Colour and storage variants count as one product.</li>
<li><strong>Explain and verify.</strong> The AI writes short statements and must cite the step each fact came from. Code checks every statement against the data. Wrong statements get one rewrite; anything still wrong is deleted.</li>
</ol>
<p>Selecting a product opens a cart approval. You have to tick consent, the server signs the exact cart, and checkout is refused if anything changed. In this demo no money is ever charged.</p>
HTML,
            ],
            'about' => [
                'title' => 'About this project',
                'body' => '<p>ShopMate is a portfolio project: a product comparison service built with Laravel, MySQL and jQuery. The public demo uses a fictional catalog (made-up brands, stores and prices), so nothing on this site is a real offer.</p>',
            ],
        ];

        foreach ($pages as $slug => $data) {
            Page::updateOrCreate(['slug' => $slug], $data);
        }
    }
}
