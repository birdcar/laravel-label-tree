<?php

declare(strict_types=1);

namespace Birdcar\LabelTree\Console;

use Birdcar\LabelTree\Exceptions\CycleDetectedException;
use Birdcar\LabelTree\Exceptions\SelfReferenceException;
use Birdcar\LabelTree\Models\Label;
use Birdcar\LabelTree\Models\LabelRelationship;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class RelationshipCreateCommand extends Command
{
    /** @var string */
    protected $signature = 'label-tree:relationship:create
        {parent : Parent label slug}
        {child : Child label slug}';

    /** @var string */
    protected $description = 'Create a new relationship between two labels';

    public function handle(): int
    {
        $parent = Label::where('slug', $this->argument('parent'))->first();
        $child = Label::where('slug', $this->argument('child'))->first();

        if ($parent === null) {
            $this->error("Parent label not found: {$this->argument('parent')}");

            return Command::FAILURE;
        }

        if ($child === null) {
            $this->error("Child label not found: {$this->argument('child')}");

            return Command::FAILURE;
        }

        try {
            LabelRelationship::create([
                'parent_label_id' => $parent->id,
                'child_label_id' => $child->id,
            ]);

            $this->info("Relationship created: {$parent->slug} -> {$child->slug}");

            return Command::SUCCESS;
        } catch (SelfReferenceException $e) {
            $this->error('Cannot create self-referential relationship.');

            return Command::FAILURE;
        } catch (CycleDetectedException $e) {
            $this->error('Cannot create relationship: would form a cycle.');

            return Command::FAILURE;
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed') || str_contains($e->getMessage(), 'Duplicate entry')) {
                $this->error('Relationship already exists.');

                return Command::FAILURE;
            }
            throw $e;
        }
    }
}
