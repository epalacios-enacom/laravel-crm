<?php

namespace Webkul\EnacomLeadOrg\DataGrids;

use Webkul\Ui\DataGrid\DataGrid;
use Illuminate\Support\Facades\DB;

class LeadOrgDataGrid extends DataGrid
{
    public function prepareQueryBuilder()
    {
        $query = DB::table('leads')
            ->leftJoin('organizations', 'leads.organization_id', '=', 'organizations.id')
            ->select(
                'leads.id',
                'leads.title',
                'leads.created_at',
                'leads.user_id',
                DB::raw('COALESCE(organizations.name, "") as organization_name')
            );

        $orgIds = (array) request('organization_ids', []);
        $orgIds = array_values(array_filter($orgIds));
        if (count($orgIds)) {
            $query->whereIn('leads.organization_id', $orgIds);
        } else {
            $orgId = request('organization_id');
            if ($orgId) {
                $query->where('leads.organization_id', $orgId);
            }
        }

        $org = request('organization_name');
        if ($org) {
            $query->where('organizations.name', 'like', '%'.$org.'%');
        }

        $this->addFilter('organization_name', 'organizations.name');
        $this->setQueryBuilder($query);
    }

    public function addColumns()
    {
        $this->addColumn([
            'index' => 'id',
            'label' => 'ID',
            'type' => 'number',
            'sortable' => true,
            'filterable' => true,
            'searchable' => false,
        ]);

        $this->addColumn([
            'index' => 'title',
            'label' => 'Título',
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index' => 'organization_name',
            'label' => 'Organización',
            'type' => 'string',
            'sortable' => true,
            'filterable' => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => 'Creado',
            'type' => 'date',
            'sortable' => true,
            'filterable' => true,
            'searchable' => false,
        ]);
    }
}
