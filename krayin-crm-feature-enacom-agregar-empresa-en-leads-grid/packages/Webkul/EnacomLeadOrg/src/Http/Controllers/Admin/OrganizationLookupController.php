<?php

namespace Webkul\EnacomLeadOrg\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationLookupController
{
    public function search(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $limit = (int) ($request->input('limit', 20));
        if ($limit < 1) {
            $limit = 20;
        }

        $query = DB::table('organizations')->select('id', 'name');
        if ($q !== '') {
            $query->where('name', 'like', '%'.$q.'%');
        }
        $items = $query->orderBy('name')->limit($limit)->get();
        return response()->json($items);
    }
}

