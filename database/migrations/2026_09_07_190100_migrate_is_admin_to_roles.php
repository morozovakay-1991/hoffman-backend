<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class () extends Migration {
    private const SUPER_ADMIN_EMAIL = 'admin@test.com';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        DB::table('users')->where('is_admin', true)->orderBy('id')->get()->each(function (object $user) use ($superAdminRole, $adminRole): void {
            $role = $user->email === self::SUPER_ADMIN_EMAIL ? $superAdminRole : $adminRole;
            $this->assignRole($user->id, $role->id);
        });

        $existingSuperAdmin = DB::table('users')->where('email', self::SUPER_ADMIN_EMAIL)->first();

        if ($existingSuperAdmin !== null) {
            $this->assignRole($existingSuperAdmin->id, $superAdminRole->id);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }

    private function assignRole(int $userId, int $roleId): void
    {
        DB::table('model_has_roles')->insertOrIgnore([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('timezone');
        });

        $roleIds = Role::whereIn('name', ['super_admin', 'admin'])->pluck('id');

        DB::table('users')
            ->whereIn('id', DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->whereIn('role_id', $roleIds)
                ->pluck('model_id'))
            ->update(['is_admin' => true]);
    }
};
