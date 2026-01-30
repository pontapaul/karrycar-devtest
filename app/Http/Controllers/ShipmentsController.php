<?php

namespace App\Http\Controllers;

use App\Models\Referent;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShipmentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Shipments/Index', [
            'shipments' => Shipment::with('referents', 'team')->limit(100)->get(),
        ]);
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Shipment $shipment)
    {
        return Inertia::render('Shipments/Show', [
            'shipment' => $shipment->load('referents', 'team'),
        ]);
    }

    public function addReferent(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'scope' => 'required|string|in:start,end',
        ]);
        $validated['team_id'] = $shipment->team_id;
        $pivotAttributes = ['scope' => $validated['scope']];

        if ($referent = Referent::query()->where(['email' => $validated['email'], 'team_id' => $shipment->team_id])->first()) {
            if ($shipment->referents()->where(['referent_id' => $referent->id, ...$pivotAttributes])->first()) {
                return response()->json([
                    'message' => 'A referent with the same email in this team is already linked to this shipment.',
                ], 409);
            }

            $referent->update($validated);
            $shipment->referents()->save($referent, $pivotAttributes);
        } else {
            $referent = $shipment->referents()->create($validated, $pivotAttributes);
        }

        return response()->json($referent, 201);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Shipment $shipment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shipment $shipment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Shipment $shipment)
    {
        //
    }
}
