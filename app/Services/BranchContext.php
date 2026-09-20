<?php

namespace App\Services;

use App\Models\Branch;

class BranchContext
{
    private ?Branch $defaultBranch = null;

    public function default(): Branch
    {
        return $this->defaultBranch ??= Branch::query()
            ->where('slug', config('saklshi.default_branch_slug', 'batumi'))
            ->where('active', true)
            ->firstOrFail();
    }

    public function id(): int
    {
        return (int) $this->default()->id;
    }
}
