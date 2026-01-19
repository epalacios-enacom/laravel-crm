<?php

namespace Webkul\EnacomLeadOrg\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Webkul\EnacomLeadOrg\DataGrids\LeadOrgDataGrid;

class LeadOrgController
{
    public function index(Request $request)
    {
        $organizations = DB::table('organizations')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        if ($request->input('view_type') === 'board') {
            $orgIds = (array) $request->input('organization_ids', []);
            $orgIds = array_values(array_filter($orgIds));

            $query = DB::table('leads')
                ->leftJoin('organizations', 'leads.organization_id', '=', 'organizations.id')
                ->leftJoin('lead_stages', 'leads.lead_stage_id', '=', 'lead_stages.id')
                ->select(
                    'leads.id',
                    'leads.title',
                    'leads.lead_stage_id',
                    DB::raw('COALESCE(organizations.name, "") as organization_name'),
                    DB::raw('COALESCE(lead_stages.name, "Sin etapa") as stage_name')
                );

            if (count($orgIds)) {
                $query->whereIn('leads.organization_id', $orgIds);
            }

            $leads = $query->orderBy('lead_stages.id')->get();

            $columns = [];
            foreach ($leads as $lead) {
                $columns[$lead->stage_name][] = $lead;
            }

            return view('enacomleadorg::admin.leads.board', [
                'organizations' => $organizations,
                'selectedOrganizationIds' => $orgIds,
                'columns' => $columns,
            ]);
        }

        return view('enacomleadorg::admin.leads.index', [
            'organizations' => $organizations,
            'selectedOrganizationIds' => (array) $request->input('organization_ids', []),
        ]);
    }

    public function grid(Request $request)
    {
        $grid = new LeadOrgDataGrid;
        return $grid->toJson();
    }

    public function export(Request $request)
    {
        $grid = new LeadOrgDataGrid;
        $grid->prepareQueryBuilder();
        $query = $grid->getQueryBuilder();

        $ids = $request->input('ids');
        if (is_array($ids) && count($ids)) {
            $query->whereIn('leads.id', $ids);
        }

        $orgIds = (array) $request->input('organization_ids', []);
        $orgIds = array_values(array_filter($orgIds));
        if (count($orgIds)) {
            $query->whereIn('leads.organization_id', $orgIds);
        } else {
            $orgId = $request->input('organization_id');
            if ($orgId) {
                $query->where('leads.organization_id', $orgId);
            }
        }

        $org = $request->input('organization_name');
        if ($org) {
            $query->where('organizations.name', 'like', '%'.$org.'%');
        }
        $rows = $query->get();
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="leads_enacom.csv"',
        ];
        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Título', 'Organización', 'Creado']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->id,
                    $row->title,
                    $row->organization_name,
                    $row->created_at,
                ]);
            }
            fclose($out);
        };
        return Response::stream($callback, 200, $headers);
    }
}
