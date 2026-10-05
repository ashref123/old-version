<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupLegacyModules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:legacy-modules {--force : Delete without asking for confirmation} {--dry-run : Show what will be deleted without deleting anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete the legacy dispatch, agent, and franchise files and folders';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $targets = [
            'files' => [
                base_path('database/migrations/2025_10_13_051619_create_request_enquiries_table.php'),
                base_path('database/migrations/2025_11_19_065546_create_agents.php'),
                base_path('database/migrations/2025_11_20_064327_create_agents_bank_info_table.php'),
                base_path('database/migrations/2025_11_20_105522_create_agent_wallet_table.php'),
                base_path('database/migrations/2025_11_20_105842_create_agent_wallet_histroy_table.php'),
                base_path('database/migrations/2025_11_20_105842_create_agent_commission_table.php'),
                base_path('database/migrations/2025_12_25_051044_create_franchises_table.php'),
                base_path('database/migrations/2025_12_25_065505_create_franchise_details_table.php'),
                base_path('database/migrations/2025_12_25_102156_create_franchise_owner_needed_documents_table.php'),
                base_path('database/migrations/2025_12_25_115432_create_franchise_owner_documents_table.php'),
                base_path('database/migrations/2025_12_26_111128_create_franchise_wallet_table.php'),
                base_path('database/migrations/2025_12_27_045923_create_franchise_wallet_history_table.php'),
                base_path('database/migrations/2025_12_27_053557_create_franchise_bank_info_table.php'),
                base_path('database/migrations/2026_01_05_115219_create_franchise_promo_code_table.php'),
                base_path('database/migrations/2026_01_19_071123_update_franchise_foreign_relations_table.php'),
                app_path('Models/Request/RequestEnquiry.php'),
                app_path('Http/Controllers/Web/Admin/DispatcherProCreateRequestController.php'),
                app_path('Http/Controllers/Web/Admin/AgentCreateRequestController.php'),
                app_path('Http/Controllers/DispatcherProController.php'),
                app_path('Http/Controllers/AgentController.php'),
                app_path('Http/Controllers/AgentCommissionController.php'),
                app_path('Http/Controllers/FranchiseDetailController.php'),
                app_path('Http/Controllers/FranchiseDriverController.php'),
                app_path('Http/Controllers/FranchiseOwnerDashBoardController.php'),
                app_path('Http/Controllers/FranchiseOwnerManagementController.php'),
                app_path('Http/Controllers/FranchisePromoController.php'),
                app_path('Models/Admin/Agents.php'),
                app_path('Models/Admin/Franchise.php'),
                app_path('Models/Admin/FranchiseDetail.php'),
                app_path('Models/Admin/FranchiseOwnerDocument.php'),
                app_path('Models/Admin/FranchiseOwnerNeededDocument.php'),
                app_path('Models/Admin/FranchisePromo.php'),
                app_path('Models/Payment/AgentBankInfo.php'),
                app_path('Models/Payment/AgentWallet.php'),
                app_path('Models/Payment/AgentWalletHistory.php'),
                app_path('Models/Payment/AgentWalletHistroy.php'),
                app_path('Models/Payment/FranchiseBankInfo.php'),
                app_path('Models/Payment/FranchiseWallet.php'),
                app_path('Models/Payment/FranchiseWalletHistory.php'),
                app_path('Base/Filters/Admin/RequestEnquiryFilter.php'),
                app_path('Base/Filters/Admin/AgentFilter.php'),
                app_path('Base/Filters/Admin/FranchiseFilter.php'),
                app_path('Base/Filters/Admin/FranchisePromoFilter.php'),
                app_path('Transformers/Agent/AgentBankInfoTransformer.php'),
                app_path('Transformers/Payment/AgentWalletHistroyTransformer.php'),
                app_path('Transformers/Payment/AgentWalletHistoryTransformer.php'),
                app_path('Transformers/Payment/FranchiseWalletHistoryTransformer.php'),
                app_path('Transformers/Payment/FranchiseWalletTransformer.php'),
                app_path('Transformers/Franchise/FranchiseBankInfoTransformer.php'),
                resource_path('js/Components/dispatch-menu-pro.vue'),
                resource_path('js/Components/dispatch-pro-nav-bar.vue'),
                resource_path('js/Components/footer-dispatcher-pro.vue'),
                resource_path('js/Components/right-bar-dispatcher-pro.vue'),
                resource_path('js/Components/agent-menu.vue'),
                resource_path('js/Components/agent-nav-bar.vue'),
                resource_path('js/Components/right-bar-agent.vue'),
                resource_path('js/Components/agentFooter.vue'),
                resource_path('js/Pages/Auth/DispatchProLogin.vue'),
                resource_path('js/Pages/Auth/AgentLogin.vue'),
                resource_path('js/Pages/pages/dispatch-pro-profile-edit.vue'),
                resource_path('js/Pages/pages/agent-profile-edit.vue'),
                base_path('routes/web/dispatchPro.php'),
                base_path('routes/web/agent.php'),
                base_path('routes/web/franchise.php'),
            ],
            'directories' => [
                app_path('Transformers/Agent'),
                app_path('Transformers/Franchise'),
                resource_path('js/Layouts/DispatcherPro'),
                resource_path('js/Layouts/Agent'),
                resource_path('js/Pages/dispatch'),
                resource_path('js/Pages/agent'),
                resource_path('js/Pages/agent/withdrawal_request'),
                resource_path('js/Pages/franchise/withdrawal_request'),
                resource_path('js/Pages/pages/dispatch'),
                resource_path('js/Pages/pages/agent_management'),
                resource_path('js/Pages/pages/withdrawal_request_agent'),
                resource_path('js/Pages/pages/franchise_drivers'),
                resource_path('js/Pages/pages/franchise_owner_needed_documents'),
                resource_path('js/Pages/pages/franchise_promo'),
                resource_path('js/Pages/pages/franchiseowner_report'),
                resource_path('js/Pages/pages/franchiseowner-dashboard'),
                resource_path('js/Pages/pages/franchises'),
                resource_path('js/Pages/pages/manage-franchise-owner'),
                resource_path('js/Pages/pages/withdrawal_request_franchise'),
            ],
        ];
        $userModelPath = app_path('Models/User.php');
        $userModelOriginal = null;
        $userModelUpdated = null;

        $existing = [
            'files' => [],
            'directories' => [],
        ];

        foreach ($targets['files'] as $path) {
            if (File::exists($path)) {
                $existing['files'][] = $path;
            }
        }

        foreach ($targets['directories'] as $path) {
            if (File::isDirectory($path)) {
                $existing['directories'][] = $path;
            }
        }

        if (File::exists($userModelPath)) {
            $userModelOriginal = File::get($userModelPath);
            $userModelUpdated = $this->removeLegacyUserRelations($userModelOriginal);
        }

        $hasUserModelChanges = $userModelOriginal !== null
            && $userModelUpdated !== null
            && $userModelUpdated !== $userModelOriginal;

        if (empty($existing['files']) && empty($existing['directories']) && ! $hasUserModelChanges) {
            return $this->info('Nothing to delete.');
        }

        $this->line('Files to delete:');
        foreach ($existing['files'] as $path) {
            $this->line(' - ' . $path);
        }

        $this->line('Directories to delete:');
        foreach ($existing['directories'] as $path) {
            $this->line(' - ' . $path);
        }

        if ($hasUserModelChanges) {
            $this->line('User model will also be updated:');
            $this->line(' - ' . $userModelPath);
        }

        if ($this->option('dry-run')) {
            return $this->info('Dry run complete.');
        }

        if (! $this->option('force') && ! $this->confirm('Delete these files and folders?')) {
            return self::SUCCESS;
        }

        foreach ($existing['files'] as $path) {
            File::delete($path);
        }

        foreach ($existing['directories'] as $path) {
            File::deleteDirectory($path);
        }

        if ($hasUserModelChanges) {
            File::put($userModelPath, $userModelUpdated);
        }

        $this->info('Legacy modules deleted successfully.');

        return self::SUCCESS;
    }

    /**
     * Remove the legacy agent and franchise relations from the User model.
     */
    protected function removeLegacyUserRelations(string $contents): string
    {
        $patterns = [
            '/^\s*use App\\\\Models\\\\Admin\\\\Agents;\R/m',
            '/^\s*use App\\\\Models\\\\Admin\\\\Franchise;\R/m',
            '/^\s*use App\\\\Models\\\\Admin\\\\FranchiseDetail;\R/m',
            '/^\s*public function agent\(\)\R\s*\{\R\s*return \$this->hasOne\(Agents::class, \'user_id\', \'id\'\);\R\s*\}\R?/m',
            '/^\s*public function franchise\(\)\R\s*\{\R\s*return \$this->hasOne\(Franchise::class, \'user_id\', \'id\'\)->withTrashed\(\);\R\s*\}\R?/m',
            '/^\s*public function franchiseDetail\(\)\R\s*\{\R\s*return \$this->hasOne\(FranchiseDetail::class, \'user_id\', \'id\'\);\R\s*\}\R?/m',
        ];

        $updatedContents = preg_replace($patterns, '', $contents) ?? $contents;

        return preg_replace("/\n{3,}/", "\n\n", $updatedContents) ?? $updatedContents;
    }
}
