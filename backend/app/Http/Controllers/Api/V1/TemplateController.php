<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventType;
use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'event_type' => ['nullable', Rule::enum(EventType::class)],
        ]);

        $templates = Template::query()
            ->where('is_active', true)
            ->when($data['event_type'] ?? null, fn ($q, $type) => $q->whereJsonContains('event_types', $type))
            ->orderBy('sort_order')
            ->get();

        return TemplateResource::collection($templates);
    }
}
