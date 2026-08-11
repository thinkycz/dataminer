<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;

class AskClarifyingQuestionsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Ask the user clarifying questions when their prompt is too vague or lacks required details. Provide options to choose from, optionally recommending the best one.';
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()
                ->description('The clarifying question to ask the user.')
                ->required(),
            'options' => $schema->array()
                ->items($schema->string())
                ->description('List of multiple-choice options for the user. Do NOT prefix the options with letters like "A:", "B.", or "A)". Only provide the raw description, as the letter badges are added automatically by the UI.')
                ->required(),
            'recommended_option' => $schema->string()
                ->description('One of the options from the list that is recommended by the AI.')
                ->required(),
        ];
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): string
    {
        try {
            $question = $this->question($request['question'] ?? null);
            $options = $this->options($request['options'] ?? null);
            $recommended = $this->recommendedOption($request['recommended_option'] ?? null, $options);

            return $this->json([
                'question' => $question,
                'options' => $options,
                'recommended_option' => $recommended,
            ]);
        } catch (RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()]);
        }
    }

    /**
     * Normalize question.
     */
    private function question(mixed $value): string
    {
        if (!\is_string($value) || \mb_trim($value) === '') {
            throw new RuntimeException('Clarifying question must be a non-empty string.');
        }

        return \mb_trim($value);
    }

    /**
     * Normalize options.
     *
     * @return array<int, string>
     */
    private function options(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new RuntimeException('Clarifying options must be an array.');
        }

        $options = [];
        foreach ($value as $option) {
            if (!\is_string($option) || \mb_trim($option) === '') {
                continue;
            }

            $normalized = \mb_trim($option);
            if ($this->hasOptionPrefix($normalized)) {
                throw new RuntimeException('Clarifying options must not include letter prefixes like A:, B), or C.');
            }

            if (!\in_array($normalized, $options, true)) {
                $options[] = $normalized;
            }
        }

        if (\count($options) < 2 || \count($options) > 5) {
            throw new RuntimeException('Clarifying options must include between 2 and 5 unique options.');
        }

        return $options;
    }

    /**
     * Normalize recommended option.
     *
     * @param array<int, string> $options
     */
    private function recommendedOption(mixed $value, array $options): string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!\is_string($value)) {
            throw new RuntimeException('recommended_option must be one of the provided options.');
        }

        $recommended = \mb_trim($value);
        if (!\in_array($recommended, $options, true)) {
            throw new RuntimeException('recommended_option must be one of the provided options.');
        }

        return $recommended;
    }

    /**
     * Detect option labels that the UI is responsible for rendering.
     */
    private function hasOptionPrefix(string $value): bool
    {
        return \preg_match('/^[A-Z][:.)-]\\s*/i', $value) === 1;
    }

    /**
     * Encode tool response.
     *
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        $encoded = \json_encode($payload);

        return \is_string($encoded) ? $encoded : '[]';
    }
}
