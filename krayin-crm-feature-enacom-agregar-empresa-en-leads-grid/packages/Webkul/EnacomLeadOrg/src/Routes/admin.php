<?php

use Illuminate\Support\Facades\Route;
use Webkul\EnacomLeadOrg\Http\Controllers\Admin\LeadOrgController;

Route::group(['middleware' => ['web', 'admin']], function () {
    Route::get('admin/enacom-test', function() { return 'ENACOM PACKAGE IS ACTIVE'; });
    Route::get('admin/leads', [LeadOrgController::class, 'index'])->name('admin.leads.index');
    Route::get('admin/leads/grid', [LeadOrgController::class, 'grid'])->name('admin.leads.grid');
    Route::get('admin/leads/export', [LeadOrgController::class, 'export'])->name('admin.leads.export');
    Route::get('admin/organizations/search', [\Webkul\EnacomLeadOrg\Http\Controllers\Admin\OrganizationLookupController::class, 'search'])->name('admin.organizations.search');
});
