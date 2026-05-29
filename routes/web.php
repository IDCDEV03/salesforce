<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SalesDealController;
use App\Http\Controllers\CompanyController; 
use App\Http\Controllers\CourseController; // เรียกใช้งาน Controller คอร์สเรียนที่เราสร้างใหม่
use App\Http\Controllers\UserController; // เพิ่มใหม่สำหรับโมดูล 5 ระบบจัดการสิทธิ์และผู้ใช้งาน
use App\Http\Controllers\AuthController; // เพิ่มใหม่สำหรับระบบเข้าสู่ระบบ Login / Logout

// ==========================================
// ระบบ Login & Logout (เปิดให้เข้าถึงได้โดยไม่ต้องผ่านการตรวจสิทธิ์)
// ==========================================
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 🚀 เส้นทางพิเศษสำหรับสร้างบัญชี Admin แรกลงระบบแบบด่วน (อัปเดตข้อมูลบัญชีตามที่คุณเลือก)
Route::get('/setup-admin', function() {
    \App\Models\User::updateOrCreate(
        ['email' => 'lesforce@company.com'],
        [
            'name' => 'lesforce',
            'password' => \Illuminate\Support\Facades\Hash::make('ID:@lesforce'),
            'role' => 'admin'
        ]
    );
    return 'เปลี่ยนบัญชี Admin และรหัสผ่านใหม่สำเร็จแล้ว! <br><br> อีเมลเข้าใช้งาน: <b>lesforce@company.com</b> <br> รหัสผ่าน: <b>ID:@lesforce</b> <br><br> <a href="/login">คลิกที่นี่เพื่อไปหน้า Login</a>';
});

// ==========================================
// 🔒 กลุ่มเส้นทางความปลอดภัย (ต้องเข้าสู่ระบบผ่าน Middleware Auth เท่านั้น)
// ==========================================
Route::middleware(['auth'])->group(function () {

    // หน้าแรกของเว็บ: ระบบแดชบอร์ดสรุปผล (โมดูล 3)
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ➕ ส่วนที่เพิ่มใหม่: Route API สำหรับดึงข้อมูลกราฟเปรียบเทียบผลงานฝ่ายขายแบบ Real-time
    Route::get('/dashboard/api/sales-comparison', [DashboardController::class, 'salesComparisonApi'])->name('dashboard.api.sales-comparison');

    // ➕ ส่วนที่เพิ่มใหม่: หน้าแดชบอร์ดข้อมูลส่วนตัวสำหรับพนักงานขาย (User Sales Dashboard)
    Route::get('/sales/dashboard', [DashboardController::class, 'salesDashboard'])->name('sales.dashboard');

    // ➕ ส่วนที่แก้ไข: หน้าแดชบอร์ดส่วนบุคคล (ปรับมาดึงข้อมูลการคำนวณผ่านฟังก์ชันของ DashboardController)
    Route::get('/dashboard/sales', [DashboardController::class, 'salesDashboard'])->name('dashboard.sales');

    // หน้าจัดการข้อมูลลูกค้า (โมดูล 1)
    Route::resource('customers', CustomerController::class);

    // หน้าบันทึกงานขายและติดตามสถานะ (โมดูล 2)
    Route::resource('deals', SalesDealController::class);

    // หน้าจัดการข้อมูลลูกค้าบริษัท (เพิ่มใหม่สำหรับรองรับข้อมูล Corporate จาก Sales Report)
    Route::resource('companies', CompanyController::class);

    // เส้นทางสำหรับกดเพิ่มคอร์สใหม่แบบด่วนผ่านหน้าฟอร์ม (เพิ่มใหม่)
    Route::post('/courses/quick-store', [SalesDealController::class, 'quickStoreCourse'])->name('courses.quick-store');

    // เส้นทางสำหรับแสดงรายการ แก้ไข และลบ คอร์ส (เพิ่มใหม่สำหรับระบบจัดการคอร์ส)
    Route::get('/courses/old', [SalesDealController::class, 'indexCourse'])->name('courses.index.old'); // เปลี่ยนชื่อชั่วคราวเพื่อไม่ให้ชนกัน
    Route::get('/courses/{course}/edit', [SalesDealController::class, 'editCourse'])->name('courses.edit');
    Route::put('/courses/{course}', [SalesDealController::class, 'updateCourse'])->name('courses.update');
    Route::delete('/courses/{course}', [SalesDealController::class, 'destroyCourse'])->name('courses.destroy');

    // เส้นทางสำหรับเปิดหน้าจัดการคอร์สในแต่ละดีล (เพิ่มใหม่เชื่อมโยงจากปุ่มในหน้า index)
    Route::get('/deals/{id}/items', [SalesDealController::class, 'items'])->name('deals.items');

    // เส้นทางสำหรับบันทึกคอร์สเรียนย่อยเพิ่มเข้าไปในดีล (เพิ่มใหม่ แก้ไขปัญหา Route deals.store-item not defined)
    Route::post('/deals/{id}/items', [SalesDealController::class, 'storeItem'])->name('deals.store-item');

    // เส้นทางเพิ่มเติมเพื่อรองรับชื่อ sales_deals.index (แก้ไขปัญหา Route sales_deals.index not defined)
    Route::get('/sales-deals', [SalesDealController::class, 'index'])->name('sales_deals.index');

    // เส้นทางเพิ่มเติมเพื่อรองรับชื่อ sales_deals.items.store (แก้ไขปัญหา Route sales_deals.items.store not defined)
    Route::post('/sales-deals/{id}/items', [SalesDealController::class, 'storeItem'])->name('sales_deals.items.store');

    // เส้นทางสำหรับลบรายการคอร์สเรียนย่อยออกจากดีล (เพิ่มใหม่สำหรับลบไอเท็มย่อย)
    Route::delete('/deals/items/{id}', [SalesDealController::class, 'destroyItem'])->name('sales_deals.items.destroy'); 

    // ==========================================
    // ส่วนที่เพิ่มใหม่สำหรับระบบพิมพ์ใบเสนอราคา (Quotation)
    // ==========================================
    Route::get('/deals/{id}/quotation', [SalesDealController::class, 'printQuotation'])->name('deals.quotation');

    // ==========================================
    // ส่วนที่เพิ่มใหม่สำหรับระบบจัดการคอร์สเรียน (Master Data คอร์สแยกส่วน)
    // ==========================================
    Route::get('/master/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/master/courses/create', [CourseController::class, 'create'])->name('courses.create');
    Route::post('/master/courses', [CourseController::class, 'store'])->name('courses.store');

    // ==========================================
    // โมดูลที่ 5: ระบบจัดการผู้ใช้งานและสิทธิ์ (User & Permission)
    // ==========================================
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');

});