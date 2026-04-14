<?php

use Illuminate\Database\Migrations\Migration;
use Warp\LaravelAiCodeOrchestrator\LaravelAiCodeOrchestratorServiceProvider;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if(class_exists('\Warp\Core\Models\WarpPackage')){
            $packageExists = Warp\Core\Models\WarpPackage::query()->where('path','=','warp/laravel-ai-code-orchestrator')->exists();
            if(!$packageExists){
                $package = new Warp\Core\Models\WarpPackage();
                $package->name = 'Warp Laravel AI Code Orchestrator';
                $package->path = 'warp/laravel-ai-code-orchestrator';
                $package->isActive = true;
                $package->provider_class_name = LaravelAiCodeOrchestratorServiceProvider::class;
                $package->save();
            }

            \Illuminate\Support\Facades\Artisan::call('warp:CacheClear');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if(class_exists('\Warp\Core\Models\WarpPackage')){
            Warp\Core\Models\WarpPackage::query()->where('path','=','warp/laravel-ai-code-orchestrator')->delete();

            \Illuminate\Support\Facades\Artisan::call('warp:CacheClear');
        }
    }
};
