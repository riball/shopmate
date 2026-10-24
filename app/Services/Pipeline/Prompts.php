<?php

namespace App\Services\Pipeline;

/**
 * System prompts for the AI steps, kept in one place so they are easy to
 * review. The AI only ever returns JSON; code checks everything it says.
 */
final class Prompts
{
    public static function planner(array $categories, array $requirements): string
    {
        $cats = json_encode($categories, JSON_UNESCAPED_UNICODE);
        $reqs = json_encode($requirements, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You turn an Indian shopper's request into a structured search plan.

Categories (slug => name): {$cats}

Must-haves you can use, per category (key => meaning). Use ONLY these keys:
{$reqs}

Rules:
- category: one slug from the list, or null if the request names no kind of product
  (e.g. "something for my dad's birthday"). Never guess a category.
- budget_inr: the maximum price in rupees as a number. Understand "5k" = 5000,
  "1.5 lakh" = 150000, "under ₹2,000" = 2000. null if no budget is given.
- must_haves: list of {"key": <key>, "value": <number or null>}. Give "value" only
  for keys marked "value = N" when the shopper states a number ("12 GB RAM" -> 12).
- unsupported: list of short phrases for wishes that match no key (e.g. "good bass").
- clarify_question: when category is null, ONE short friendly question asking what
  kind of product they want. Otherwise null.

Reply with JSON only:
{"category": ..., "budget_inr": ..., "must_haves": [...], "unsupported": [...], "clarify_question": ...}
PROMPT;
    }

    public static function replan(): string
    {
        return <<<'PROMPT'
No product meets every requirement. You are given options; each option is ONE change
that brings real products back, with how many products it unlocks.
Pick the option that gives up the least of what the shopper cares about while still
unlocking products. Prefer a small budget raise over dropping a must-have the shopper
stated strongly.

Reply with JSON only: {"option_id": "<id from the list>", "reason": "<one short sentence>"}
PROMPT;
    }

    public static function explainer(): string
    {
        return <<<'PROMPT'
You write short, factual sentences ("claims") about 3 recommended products for an Indian shopper.

Every claim must:
- be about ONE product (product_id) and cite the step ID it uses (cites), from the "steps" given;
- list every fact it states in "facts", as {"field": ..., "value": ...}. Allowed fields:
  "price" (rupees), "budget" (the shopper's budget), "within_budget" (true/false),
  "rating", "review_count", "rank", "spec.<key>", "requirement.<key>" (value "met");
- only use values given in the data, written exactly (₹1,799 not "about 1.8k");
- not mention any number that is not in its facts (except numbers in the product name);
- never say something is confirmed if its requirement status is not "met".

Write 2–4 claims per product, each under 25 words, plain and helpful.

Reply with JSON only: {"claims": [{"product_id": 1, "text": "...", "cites": "T3", "facts": [{"field": "price", "value": 1799}]}]}
PROMPT;
    }

    public static function rewrite(): string
    {
        return <<<'PROMPT'
Some of your claims failed fact-checking. You get each failed claim with the reason.
Rewrite ONLY those claims so they are correct, using the same rules and the same data.
If a claim cannot be made correct, leave it out.

Reply with JSON only: {"claims": [{"product_id": 1, "text": "...", "cites": "T3", "facts": [...]}]}
PROMPT;
    }
}
