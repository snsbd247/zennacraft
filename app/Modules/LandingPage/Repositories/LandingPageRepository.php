<?php

namespace App\Modules\LandingPage\Repositories;

use App\Modules\LandingPage\Models\LandingPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LandingPageRepository
{
    public function create(array $data): LandingPage
    {
        return LandingPage::create($data);
    }

    public function update(LandingPage|int $landingPage, array $data): bool
    {
        $landingPage = $landingPage instanceof LandingPage ? $landingPage : $this->find($landingPage);

        return $landingPage ? $landingPage->update($data) : false;
    }

    public function delete(LandingPage|int $landingPage): bool
    {
        $landingPage = $landingPage instanceof LandingPage ? $landingPage : $this->find($landingPage);

        return $landingPage ? (bool) $landingPage->delete() : false;
    }

    public function find(int $id): ?LandingPage
    {
        return LandingPage::find($id);
    }

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return LandingPage::latest()->paginate($perPage);
    }
}
