<?php

namespace App\Services\Scanners\Contracts;

interface RuleInterface
{
    public function id(): string;

    public function title(): string;

    public function severity(): string;

    public function category(): string;

    /**
     * @return array<int, array{
     *     rule_id: string,
     *     title: string,
     *     description: string,
     *     severity: string,
     *     category: string,
     *     file_path: string,
     *     line_start: int,
     *     code_snippet: string,
     *     fix_guidance: string,
     * }>
     */
    public function check(string $projectPath): array;
}
