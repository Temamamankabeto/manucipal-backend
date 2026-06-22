<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TranslationController extends Controller
{
    public function resources(): JsonResponse
    {
        $resources = [
            'en' => [],
            'om' => [],
            'am' => [],
        ];

        Translation::query()
            ->select(['language', 'translation_key', 'translation_value'])
            ->orderBy('translation_key')
            ->get()
            ->each(function (Translation $translation) use (&$resources) {
                if (! array_key_exists($translation->language, $resources)) {
                    return;
                }

                $resources[$translation->language][$translation->translation_key] = $translation->translation_value;
            });

        return response()->json([
            'success' => true,
            'message' => 'Translation resources retrieved successfully',
            'data' => $resources,
            'meta' => null,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Translation::query()
            ->when($request->filled('language'), fn ($q) => $q->where('language', $request->string('language')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->string('search') . '%';
                $q->where(function ($inner) use ($search) {
                    $inner->where('translation_key', 'like', $search)
                        ->orWhere('translation_value', 'like', $search);
                });
            })
            ->orderBy('language')
            ->orderBy('translation_key');

        $translations = $query->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Translations retrieved successfully',
            'data' => $translations->items(),
            'meta' => [
                'current_page' => $translations->currentPage(),
                'last_page' => $translations->lastPage(),
                'per_page' => $translations->perPage(),
                'total' => $translations->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'string', 'max:10', Rule::in(['en', 'om', 'am'])],
            'translation_key' => ['required', 'string', 'max:255'],
            'translation_value' => ['required', 'string'],
        ]);

        $translation = DB::transaction(fn () => Translation::updateOrCreate(
            ['language' => $validated['language'], 'translation_key' => $validated['translation_key']],
            ['translation_value' => $validated['translation_value']]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Translation saved successfully',
            'data' => $translation,
            'meta' => null,
        ], 201);
    }

    public function update(Request $request, Translation $translation): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'string', 'max:10', Rule::in(['en', 'om', 'am'])],
            'translation_key' => ['required', 'string', 'max:255'],
            'translation_value' => ['required', 'string'],
        ]);

        DB::transaction(fn () => $translation->update($validated));

        return response()->json([
            'success' => true,
            'message' => 'Translation updated successfully',
            'data' => $translation->fresh(),
            'meta' => null,
        ]);
    }

    public function destroy(Translation $translation): JsonResponse
    {
        DB::transaction(fn () => $translation->delete());

        return response()->json([
            'success' => true,
            'message' => 'Translation deleted successfully',
            'data' => null,
            'meta' => null,
        ]);
    }
}
