<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator  = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:40',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

       try {
        $product = new Product();
        $product->id = (string) Str::uuid();
        $product->name = $request->name;
        $product->type = $request->type;
        $product->slug = Str::slug($request->name);
        $product->description = $request->description ?? '';
        $product->version = $request->version ?? '1.0.0';
        $product->source = $request->source ?? 'custom';
        $product->source_product_id = $request->source_product_id ?? null;
        $product->source_metadata = $request->source_metadata ?? [];
        $product->requirements = $request->requirements ?? [];
        $product->compatibility = $request->compatibility ?? [];
        $product->requires_domain_verification = $request->requires_domain_verification ?? true;
        $product->requires_hardware_verification = $request->requires_hardware_verification ?? false;
        $product->max_hardware_changes = $request->max_hardware_changes ?? 3;
        $product->offline_grace_period_days = $request->offline_grace_period_days ?? 3;
        $product->current_version = $request->current_version ?? '1.0.0';
        $product->version_history = $request->version_history ?? [];
        $product->check_in_interval_days = $request->check_in_interval_days ?? 7;
        $product->support_email = $request->support_email ?? null;
        $product->support_url = $request->support_url ?? null;
        $product->support_ends_at = $request->support_ends_at ?? null;
        $product->features = $request->features ?? [];
        $product->settings = $request->settings ?? [];
        $product->status = $request->status ?? 'active';
        $product->created_by = $request->created_by ?? 'system';
        $product->metadata = $request->metadata ?? [];
        $product->save();

        return response()->json(['message' => 'Product created successfully', 'product' => $product], 200);
       } catch (\Exception $e) {
        return response()->json(['message' => 'Product creation failed', 'error' => $e->getMessage()], 500);
       }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
