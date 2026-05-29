<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::table('courses', function (Blueprint $table) {
        if (!Schema::hasColumn('courses', 'description')) {
            $table->text('description')->nullable()->after('course_name');
        }
        if (!Schema::hasColumn('courses', 'default_price')) {
            $table->decimal('default_price', 10, 2)->nullable()->after('description');
        }
        if (!Schema::hasColumn('courses', 'is_active')) {
            $table->boolean('is_active')->default(true)->after('default_price');
        }
    });
}

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            // กรณีต้องการย้อนกลับ (Rollback) ให้ลบคอลัมน์ทิ้ง
            $table->dropColumn(['description', 'default_price', 'is_active']);
        });
    }
};
