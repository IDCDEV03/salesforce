<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesDeal;
use App\Models\User; // เพิ่มโมเดล User สำหรับดึงรายชื่อพนักงาน
use App\Models\Course; // ➕ เพิ่มโมเดล Course สำหรับดึงรายชื่อคอร์สเรียนทำฟิลเตอร์
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 🔒 ป้องกันพนักงานพิมพ์ URL เข้ามาตรงๆ ถ้าไม่ใช่ Admin หรือ Manager ให้ดีดเตะไปหน้างานขายทันที
        if (auth()->check() && !auth()->user()->isAdmin() && strtolower(auth()->user()->role) !== 'manager') {
            return redirect()->route('deals.index');
        }

        // 🔮 โมดูลที่ 5: โค้ดจำลองสิทธิ์ผู้ใช้งานระดับหัวหน้า (Admin) ชั่วคราวเพื่อใช้ทดสอบระบบตามความต้องการ
        if (!auth()->check()) {
            $mockUser = new \App\Models\User();
            $mockUser->id = 1;
            $mockUser->name = 'lesforce';
            $mockUser->email = 'lesforce@company.com';
            $mockUser->role = 'admin'; // กำหนดสิทธิ์ให้เป็น admin (หัวหน้า) เสมอกับความต้องการ
            auth()->login($mockUser);
        }

        // 1. รับค่าปีงบประมาณ เดือน และพนักงาน ที่มีการเลือกมาจากหน้าบ้าน (ถ้าไม่มีการส่งมา ให้ใช้ปีและเดือนปัจจุบันเป็นค่าเริ่มต้น)
        $selectedYear = $request->get('fiscal_year', date('Y'));
        $selectedMonth = $request->get('fiscal_month', date('m'));
        $selectedSalesPerson = $request->get('sales_person_id') ?? $request->get('table_sales_persons'); // รองรับการส่งทั้งแบบตัวแปรเดี่ยวและรูปแบบอาเรย์ (table_sales_persons[])
        $selectedStatus = $request->get('table_status') ?? $request->get('status'); // ดึงค่าตัวกรองสถานะจากหน้าบ้าน

        // 2. สร้าง Query ดึงข้อมูลดีลที่มีเงื่อนไขกรองตามปีและเดือนของวันที่บันทึกดีล (deal_date)
        $query = SalesDeal::with(['items.course'])
            ->whereYear('deal_date', $selectedYear)
            ->whereMonth('deal_date', $selectedMonth);

        // หากมีการเลือกพนักงาน และค่านั้นไม่ใช่ค่าว่างหรือ 'all' (พนักงานทั้งหมด) ให้ทำการกรองข้อมูล
        if (!empty($selectedSalesPerson) && $selectedSalesPerson !== 'all' && $selectedSalesPerson !== ['all']) {
            $query->where(function($q) use ($selectedSalesPerson) {
                $tableName = (new SalesDeal())->getTable();
                $isSpread = is_array($selectedSalesPerson);
                
                if (Schema::hasColumn($tableName, 'user_id')) {
                    if ($isSpread) {
                        $q->orWhereIn('user_id', $selectedSalesPerson);
                    } else {
                        $q->orWhere('user_id', $selectedSalesPerson);
                    }
                }
                
                if (Schema::hasColumn($tableName, 'sales_person_id')) {
                    if ($isSpread) {
                        $q->orWhereIn('sales_person_id', $selectedSalesPerson);
                    } else {
                        $q->orWhere('sales_person_id', $selectedSalesPerson);
                    }
                }

                // กรณีที่ระบบใช้ Eloquent Relation 'user'
                $q->orWhereHas('user', function($subQuery) use ($selectedSalesPerson, $isSpread) {
                    if ($isSpread) {
                        $subQuery->whereIn('id', $selectedSalesPerson);
                    } else {
                        $subQuery->where('id', $selectedSalesPerson);
                    }
                });
            });
        }

        // ➕ (ส่วนที่ปรับปรุงใหม่) ทำ Mapping กรองสถานะให้ตรงตามฐานข้อมูล ป้องกันเคสตัวพิมพ์เล็ก-ใหญ่ผิดพลาด
        if (!empty($selectedStatus) && $selectedStatus !== 'all') {
            $statusMap = [
                'closed' => 'Closed Sale',
                'closed sale' => 'Closed Sale',
                'closed_sale' => 'Closed Sale',
                'following' => 'Following',
                'forecast' => 'Forecast'
            ];
            $normalizedStatus = $statusMap[strtolower($selectedStatus)] ?? $selectedStatus;
            $query->where('status', $normalizedStatus);
        }

        $deals = $query->get();

        // ตั้งค่าตัวแปรเริ่มต้นสถิติตามปกติ
        $totalClosed = 0;
        $totalFollowing = 0;
        $totalForecast = 0;

        // ตัวแปรสำหรับเก็บยอดขาย 12 เดือน (ม.ค. - ธ.ค.) สำหรับกราฟเดิม (คงไว้เพื่อป้องกันระบบหลักพัง)
        $chartData = array_fill(0, 12, 0);

        // ตัวแปรสำหรับเก็บข้อมูลรายได้แยกตามรายคอร์สเรียน (Master Data) สำหรับกราฟแท่งตัวใหม่
        $courseRevenueMap = [];

        // ➕ แผนผังสำหรับเก็บข้อมูลสรุปยอดคนและรายได้ของคอร์สเรียนแยกตาม 3 สถานะเพื่อนำไปแสดงผลบนตารางใหม่
        $closedSaleCoursesMap = [];
        $followingCoursesMap = [];
        $forecastCoursesMap = [];

        foreach ($deals as $deal) {
            // คำนวณยอดรวมเงินของแต่ละดีล
            $dealTotal = 0;
            foreach ($deal->items as $item) {
                $price = $item->price_per_person ?? 0;
                // รองรับทั้งชื่อฟิลด์ discount และ discount_per_person
                $discount = $item->discount_per_person ?? $item->discount ?? 0; 
                $qty = $item->total_person ?? 0;
                
                $itemTotal = ($price - $discount) * $qty;
                if ($itemTotal < 0) $itemTotal = 0;
                $dealTotal += $itemTotal;

                // สะสมยอดรายได้แยกตามชื่อคอร์ส (ตามสถานะที่เลือก หรือหากเลือกทั้งหมดจะยึดตาม 'Closed Sale' เป็นค่าเริ่มต้น)
                $targetCourseStatus = 'Closed Sale';
                if (!empty($selectedStatus) && $selectedStatus !== 'all') {
                    $statusMap = [
                        'closed' => 'Closed Sale',
                        'closed sale' => 'Closed Sale',
                        'closed_sale' => 'Closed Sale',
                        'following' => 'Following',
                        'forecast' => 'Forecast'
                    ];
                    $targetCourseStatus = $statusMap[strtolower($selectedStatus)] ?? $selectedStatus;
                }

                if ($deal->status === $targetCourseStatus && $item->course) {
                    $courseName = $item->course->course_name ?? 'ไม่ระบุชื่อคอร์ส';
                    if (!isset($courseRevenueMap[$courseName])) {
                        $courseRevenueMap[$courseName] = 0;
                    }
                    $courseRevenueMap[$courseName] += $itemTotal;
                }

                // ➕ สะสมจำนวนคนและยอดเงินแยกตามชื่อคอร์สเรียนและสถานะของดีลเพื่อป้อนข้อมูลเข้าตารางใหม่
                if ($item->course) {
                    $courseName = $item->course->course_name ?? 'ไม่ระบุชื่อคอร์ส';
                    
                    if ($deal->status === 'Closed Sale') {
                        if (!isset($closedSaleCoursesMap[$courseName])) {
                            $closedSaleCoursesMap[$courseName] = ['course_name' => $courseName, 'total_person' => 0, 'total_revenue' => 0];
                        }
                        $closedSaleCoursesMap[$courseName]['total_person'] += $qty;
                        $closedSaleCoursesMap[$courseName]['total_revenue'] += $itemTotal;
                    } elseif ($deal->status === 'Following') {
                        if (!isset($followingCoursesMap[$courseName])) {
                            $followingCoursesMap[$courseName] = ['course_name' => $courseName, 'total_person' => 0, 'total_revenue' => 0];
                        }
                        $followingCoursesMap[$courseName]['total_person'] += $qty;
                        $followingCoursesMap[$courseName]['total_revenue'] += $itemTotal;
                    } elseif ($deal->status === 'Forecast') {
                        if (!isset($forecastCoursesMap[$courseName])) {
                            $forecastCoursesMap[$courseName] = ['course_name' => $courseName, 'total_person' => 0, 'total_revenue' => 0];
                        }
                        $forecastCoursesMap[$courseName]['total_person'] += $qty;
                        $forecastCoursesMap[$courseName]['total_revenue'] += $itemTotal;
                    }
                }
            }

            // แยกยอดเงินไปบวกตามสถานะของดีล
            if ($deal->status === 'Closed Sale') {
                $totalClosed += $dealTotal;

                // จัดยอดลง Array กราฟตามเดือน (อิงจากวันที่อัปเดต)
                if ($deal->updated_at) {
                    // Carbon ช่วยดึงตัวเลขเดือน (1-12) เอามาลบ 1 เพื่อให้ตรงกับ Index ของ Array (0-11)
                    $monthIndex = Carbon::parse($deal->updated_at)->format('n') - 1; 
                    $chartData[$monthIndex] += $dealTotal;
                }

            } elseif ($deal->status === 'Following') {
                $totalFollowing += $dealTotal;
                
            } elseif ($deal->status === 'Forecast') {
                $totalForecast += $dealTotal;
            }
        }

        // แปลงข้อมูลรายได้คอร์สเรียนเป็นชุดข้อมูลสำหรับ Chart.js (คัดเลือกเฉพาะคอร์สที่มียอดเงิน)
        arsort($courseRevenueMap); // เรียงจากคอร์สที่ทำรายได้มากที่สุดไปน้อยสุด
        $courseLabels = array_keys($courseRevenueMap);
        $courseData = array_values($courseRevenueMap);

        // ➕ จัดเรียงลำดับคอร์สเรียนตามรายได้สุทธิจากมากไปน้อย และแปลงโครงสร้างเป็น Object เพื่อรองรับโครงสร้างในหน้า Blade View ได้ทันที
        uasort($closedSaleCoursesMap, function($a, $b) { return $b['total_revenue'] <=> $a['total_revenue']; });
        uasort($followingCoursesMap, function($a, $b) { return $b['total_revenue'] <=> $a['total_revenue']; });
        uasort($forecastCoursesMap, function($a, $b) { return $b['total_revenue'] <=> $a['total_revenue']; });

        $closedSaleCourses = collect($closedSaleCoursesMap)->map(function($data) {
            return (object)[
                'course' => (object)['course_name' => $data['course_name']],
                'total_person_sum' => $data['total_person'],
                'total_revenue_sum' => $data['total_revenue']
            ];
        })->values();

        $followingCourses = collect($followingCoursesMap)->map(function($data) {
            return (object)[
                'course' => (object)['course_name' => $data['course_name']],
                'total_person_sum' => $data['total_person'],
                'total_revenue_sum' => $data['total_revenue']
            ];
        })->values();

        $forecastCourses = collect($forecastCoursesMap)->map(function($data) {
            return (object)[
                'course' => (object)['course_name' => $data['course_name']],
                'total_person_sum' => $data['total_person'],
                'total_revenue_sum' => $data['total_revenue']
            ];
        })->values();

        // 3. คำนวณถอดภาษีมูลค่าเพิ่ม (VAT 7% แบบรวมใน Inclusive VAT) จากยอดที่ปิดการขายได้ (Closed Sale)
        $totalNet = $totalClosed / 1.07;
        $totalVat = $totalClosed - $totalNet;

        // 4. ดึงรายชื่อปีทั้งหมดที่มีอยู่ในระบบ เพื่อเอาไปทำตัวเลือก Dropdown บนหน้าแดชบอร์ด
        $availableYears = SalesDeal::selectRaw('YEAR(deal_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        // ป้องกันกรณีฐานข้อมูลว่าง ให้มีปีปัจจุบันรองรับไว้เสมอ
        if ($availableYears->isEmpty()) {
            $availableYears = collect([date('Y')]);
        }

        // 5. ดึงรายชื่อพนักงานทั้งหมดไปแสดงใน Dropdown ให้เลือก (เรียงตามชื่อ)
        $salesPersons = User::orderBy('name', 'asc')->get();

        // ➕ 6. ดึงข้อมูลสำหรับทำตัวกรองเปรียบเทียบยอดขายใน Dashboard
        $salesEmployees = User::orderBy('name', 'asc')->get();
        $coursesList = Course::orderBy('course_name', 'asc')->get();

        // =====================================================================
        // ➕ ระบบตาราง Matrix: ดึงข้อมูลยอดขายเปรียบเทียบรายบุคคล (ปรับปรุงใหม่)
        // =====================================================================
        $allCourses = clone $coursesList; 
        
        // รับค่า Array ของพนักงานและคอร์สจาก Filter Matrix
        $selectedSalesPersonIds = $request->input('sales_person_ids', []);
        $selectedCourseIds = $request->input('course_ids', []);

        // ⚡ สอดคล้องกับฟิลเตอร์หลัก: หากกรองพนักงานคนเดียวจากด้านบน ให้ดึงพนักงานคนนั้นมาแสดงในตาราง Matrix ด้วยทันที
        if (empty($selectedSalesPersonIds) && !empty($selectedSalesPerson) && $selectedSalesPerson !== 'all' && $selectedSalesPerson !== ['all']) {
            $selectedSalesPersonIds = is_array($selectedSalesPerson) ? $selectedSalesPerson : [$selectedSalesPerson];
        }

        // ถ้าไม่มีการเลือกอะไรเลย ให้ถือว่าเลือก "ทั้งหมด" เป็นค่าเริ่มต้น
        if (empty($selectedSalesPersonIds)) {
            $selectedSalesPersonIds = $salesPersons->pluck('id')->toArray();
        }
        if (empty($selectedCourseIds)) {
            $selectedCourseIds = $allCourses->pluck('id')->toArray();
        }

        // ดึงคอร์สเรียนและพนักงาน เฉพาะที่ถูกเลือก
        $matrixCoursesList = $allCourses->whereIn('id', $selectedCourseIds);
        $matrixCourses = $matrixCoursesList->pluck('course_name')->toArray();
        
        $matrixData = [];
        $filteredSalesPersons = $salesPersons->whereIn('id', $selectedSalesPersonIds);

        foreach ($filteredSalesPersons as $person) {
            $salesByCourse = [];

            // ⚡ ปรับปรุงเงื่อนไขสถานะในตาราง Matrix ให้เปลี่ยนตามสถานะฟิลเตอร์หลัก
            $matrixStatus = 'Closed Sale';
            if (!empty($selectedStatus) && $selectedStatus !== 'all') {
                $statusMap = [
                    'closed' => 'Closed Sale',
                    'closed sale' => 'Closed Sale',
                    'closed_sale' => 'Closed Sale',
                    'following' => 'Following',
                    'forecast' => 'Forecast'
                ];
                $matrixStatus = $statusMap[strtolower($selectedStatus)] ?? $selectedStatus;
            }

            // ดึงดีลตามสถานะ ปี และเดือนปัจจุบันที่เลือกอยู่
            $personDeals = SalesDeal::with(['items.course'])
                ->whereYear('deal_date', $selectedYear)
                ->whereMonth('deal_date', $selectedMonth)
                ->where('status', $matrixStatus)
                ->where(function($q) use ($person) {
                    $tableName = (new SalesDeal())->getTable();
                    if (Schema::hasColumn($tableName, 'user_id')) {
                        $q->orWhere('user_id', $person->id);
                    }
                    if (Schema::hasColumn($tableName, 'sales_person_id')) {
                        $q->orWhere('sales_person_id', $person->id);
                    }
                    $q->orWhereHas('user', function($subQuery) use ($person) {
                        $subQuery->where('id', $person->id);
                    });
                })->get();

            // คำนวณยอดเงินแยกลงในแต่ละคอร์สที่ถูกเลือก
            foreach ($matrixCoursesList as $course) {
                $courseSum = 0;
                foreach ($personDeals as $deal) {
                    foreach ($deal->items as $item) {
                        $itemCourseId = $item->course_id ?? ($item->course ? $item->course->id : null);
                        
                        if ($itemCourseId == $course->id) {
                            $price = $item->price_per_person ?? 0;
                            $discount = $item->discount_per_person ?? $item->discount ?? 0; 
                            $qty = $item->total_person ?? 0;
                            
                            $itemTotal = ($price - $discount) * $qty;
                            if ($itemTotal < 0) $itemTotal = 0;
                            
                            $courseSum += $itemTotal;
                        }
                    }
                }
                $salesByCourse[$course->course_name] = $courseSum;
            }

            $matrixData[] = (object)[
                'name' => $person->name,
                'sales' => $salesByCourse
            ];
        }
        // =====================================================================

        return view('dashboard', compact(
            'totalClosed',
            'totalFollowing',
            'totalForecast',
            'chartData',
            'totalNet',
            'totalVat',
            'availableYears',
            'selectedYear',
            'selectedMonth',
            'courseLabels',
            'courseData',
            'salesPersons',
            'selectedSalesPerson',
            'selectedStatus', // ➕ ส่งตัวแปรสถานะกลับไปแสดงผลค้างไว้ที่ช่อง Dropdown ของ View
            'closedSaleCourses',
            'followingCourses',
            'forecastCourses',
            'salesEmployees', 
            'coursesList',    
            'allCourses',
            'selectedSalesPersonIds',
            'selectedCourseIds',
            'matrixCourses',
            'matrixData'
        ));
    }

    // =====================================================================
    // ➕ ฟังก์ชัน API สำหรับกราฟแท่งแนวนอน (ปรับปรุงให้รองรับตัวกรองตามหน้าหลักเต็มระบบ)
    // =====================================================================
    public function salesComparisonApi(Request $request)
    {
        // 1. รับค่า ID พนักงานและคอร์สจากที่กดเลือก
        $salesIds = $request->has('sales_ids') && $request->sales_ids != '' ? explode(',', $request->sales_ids) : [];
        $courseIds = $request->has('course_ids') && $request->course_ids != '' ? explode(',', $request->course_ids) : [];

        // ⚡ รับค่าวันเวลา และสถานะปัจจุบัน เพื่อให้การคำนวณกราฟเปรียบเทียบตรงกับการกรองบน Dashboard
        $selectedYear = $request->get('fiscal_year', date('Y'));
        $selectedMonth = $request->get('fiscal_month', date('m'));
        $apiStatus = $request->get('status') ?? $request->get('table_status');

        $apiTargetStatus = 'Closed Sale';
        if (!empty($apiStatus) && $apiStatus !== 'all') {
            $statusMap = [
                'closed' => 'Closed Sale',
                'closed sale' => 'Closed Sale',
                'closed_sale' => 'Closed Sale',
                'following' => 'Following',
                'forecast' => 'Forecast'
            ];
            $apiTargetStatus = $statusMap[strtolower($apiStatus)] ?? $apiStatus;
        }

        // 2. ดึงข้อมูลพนักงานที่ถูกเลือก มาทำเป็นแกน Y
        $employees = User::whereIn('id', $salesIds)->get();
        $labels = $employees->pluck('name')->toArray();

        // 3. ดึงข้อมูลคอร์สเรียนที่ถูกเลือก มาสร้างแท่งสีข้อมูล (Datasets)
        $courses = Course::whereIn('id', $courseIds)->get();

        // 4. ชุดสีสำหรับกราฟแท่งแยกตามคอร์ส
        $backgroundColors = [
            'rgba(16, 124, 65, 0.85)', 'rgba(232, 115, 41, 0.85)', 
            'rgba(28, 99, 137, 0.85)', 'rgba(121, 82, 179, 0.85)', 
            'rgba(220, 53, 69, 0.85)', 'rgba(255, 193, 7, 0.85)'
        ];
        $borderColors = [
            'rgb(16, 124, 65)', 'rgb(232, 115, 41)', 
            'rgb(28, 99, 137)', 'rgb(121, 82, 179)', 
            'rgb(220, 53, 69)', 'rgb(255, 193, 7)'
        ];

        $datasets = [];
        $colorIndex = 0;

        foreach ($courses as $course) {
            $dataValues = [];

            foreach ($employees as $employee) {
                // ดึงดีลที่แมตช์ตาม ปี เดือน และสถานะที่เลือกคัดกรองเข้ามา
                $deals = SalesDeal::with(['items.course'])
                    ->whereYear('deal_date', $selectedYear)
                    ->whereMonth('deal_date', $selectedMonth)
                    ->where('status', $apiTargetStatus)
                    ->where(function($q) use ($employee) {
                        $tableName = (new SalesDeal())->getTable();
                        
                        if (Schema::hasColumn($tableName, 'user_id')) {
                            $q->orWhere('user_id', $employee->id);
                        }
                        if (Schema::hasColumn($tableName, 'sales_person_id')) {
                            $q->orWhere('sales_person_id', $employee->id);
                        }
                        $q->orWhereHas('user', function($subQuery) use ($employee) {
                            $subQuery->where('id', $employee->id);
                        });
                    })
                    ->get();

                $totalSalesForThisCourse = 0;

                // คำนวณยอดเงินสูตรเดียวกันเพื่อความถูกต้อง
                foreach ($deals as $deal) {
                    foreach ($deal->items as $item) {
                        $itemCourseId = $item->course_id ?? ($item->course ? $item->course->id : null);
                        
                        if ($itemCourseId == $course->id) {
                            $price = $item->price_per_person ?? 0;
                            $discount = $item->discount_per_person ?? $item->discount ?? 0; 
                            $qty = $item->total_person ?? 0;
                            
                            $itemTotal = ($price - $discount) * $qty;
                            if ($itemTotal < 0) $itemTotal = 0;
                            
                            $totalSalesForThisCourse += $itemTotal;
                        }
                    }
                }

                $dataValues[] = (float)$totalSalesForThisCourse;
            }

            $datasets[] = [
                'label' => $course->course_name ?? 'ไม่ระบุชื่อคอร์ส',
                'data' => $dataValues,
                'backgroundColor' => $backgroundColors[$colorIndex % count($backgroundColors)],
                'borderColor' => $borderColors[$colorIndex % count($borderColors)],
                'borderWidth' => 1
            ];

            $colorIndex++;
        }

        // 5. ส่งค่ากลับไปรูปแบบ JSON เพื่อเอาไปใช้วาดกราฟต่อในฟรอนต์เอนด์
        return response()->json([
            'labels' => $labels,
            'datasets' => $datasets
        ]);
    }

    // =====================================================================
    // 👤 ฟังก์ชันแดชบอร์ดส่วนบุคคลเฉพาะพนักงานขาย (User Personal Dashboard)
    // =====================================================================
    public function salesDashboard(Request $request)
    {
        $userId = auth()->id(); // ดึง ID ของพนักงานที่ล็อกอินอยู่
        
        $selectedYear = $request->get('fiscal_year', date('Y'));
        $selectedMonth = $request->get('fiscal_month', date('m'));
        $selectedStatus = $request->get('status', 'all');

        // สร้าง Query กรองเวลา และบังคับล็อกคัดเฉพาะ ID ของตัวเองเท่านั้นเพื่อความปลอดภัย
        $query = SalesDeal::with(['items.course'])
            ->whereYear('deal_date', $selectedYear)
            ->whereMonth('deal_date', $selectedMonth)
            ->where(function($q) use ($userId) {
                $tableName = (new SalesDeal())->getTable();
                if (Schema::hasColumn($tableName, 'user_id')) {
                    $q->orWhere('user_id', $userId);
                }
                if (Schema::hasColumn($tableName, 'sales_person_id')) {
                    $q->orWhere('sales_person_id', $userId);
                }
                $q->orWhereHas('user', function($subQuery) use ($userId) {
                    $subQuery->where('id', $userId);
                });
            });

        if (!empty($selectedStatus) && $selectedStatus !== 'all') {
            $statusMap = [
                'closed' => 'Closed Sale',
                'closed sale' => 'Closed Sale',
                'closed_sale' => 'Closed Sale',
                'following' => 'Following',
                'forecast' => 'Forecast'
            ];
            $normalizedStatus = $statusMap[strtolower($selectedStatus)] ?? $selectedStatus;
            $query->where('status', $normalizedStatus);
        }

        $deals = $query->get();

        $totalClosed = 0;
        $totalFollowing = 0;
        $totalForecast = 0;
        $chartData = array_fill(0, 12, 0);
        $courseRevenueMap = [];

        $closedSaleCoursesMap = [];
        $followingCoursesMap = [];
        $forecastCoursesMap = [];

        foreach ($deals as $deal) {
            $dealTotal = 0;
            foreach ($deal->items as $item) {
                $price = $item->price_per_person ?? 0;
                $discount = $item->discount_per_person ?? $item->discount ?? 0; 
                $qty = $item->total_person ?? 0;
                
                $itemTotal = ($price - $discount) * $qty;
                if ($itemTotal < 0) $itemTotal = 0;
                $dealTotal += $itemTotal;

                $targetCourseStatus = 'Closed Sale';
                if (!empty($selectedStatus) && $selectedStatus !== 'all') {
                    $statusMap = [
                        'closed' => 'Closed Sale',
                        'closed sale' => 'Closed Sale',
                        'closed_sale' => 'Closed Sale',
                        'following' => 'Following',
                        'forecast' => 'Forecast'
                    ];
                    $targetCourseStatus = $statusMap[strtolower($selectedStatus)] ?? $selectedStatus;
                }

                if ($deal->status === $targetCourseStatus && $item->course) {
                    $courseName = $item->course->course_name ?? 'ไม่ระบุชื่อคอร์ส';
                    if (!isset($courseRevenueMap[$courseName])) {
                        $courseRevenueMap[$courseName] = 0;
                    }
                    $courseRevenueMap[$courseName] += $itemTotal;
                }

                if ($item->course) {
                    $courseName = $item->course->course_name ?? 'ไม่ระบุชื่อคอร์ส';
                    
                    if ($deal->status === 'Closed Sale') {
                        if (!isset($closedSaleCoursesMap[$courseName])) {
                            $closedSaleCoursesMap[$courseName] = ['course_name' => $courseName, 'total_person' => 0, 'total_revenue' => 0];
                        }
                        $closedSaleCoursesMap[$courseName]['total_person'] += $qty;
                        $closedSaleCoursesMap[$courseName]['total_revenue'] += $itemTotal;
                    } elseif ($deal->status === 'Following') {
                        if (!isset($followingCoursesMap[$courseName])) {
                            $followingCoursesMap[$courseName] = ['course_name' => $courseName, 'total_person' => 0, 'total_revenue' => 0];
                        }
                        $followingCoursesMap[$courseName]['total_person'] += $qty;
                        $followingCoursesMap[$courseName]['total_revenue'] += $itemTotal;
                    } elseif ($deal->status === 'Forecast') {
                        if (!isset($forecastCoursesMap[$courseName])) {
                            $forecastCoursesMap[$courseName] = ['course_name' => $courseName, 'total_person' => 0, 'total_revenue' => 0];
                        }
                        $forecastCoursesMap[$courseName]['total_person'] += $qty;
                        $forecastCoursesMap[$courseName]['total_revenue'] += $itemTotal;
                    }
                }
            }

            if ($deal->status === 'Closed Sale') {
                $totalClosed += $dealTotal;
                if ($deal->updated_at) {
                    $monthIndex = Carbon::parse($deal->updated_at)->format('n') - 1; 
                    $chartData[$monthIndex] += $dealTotal;
                }
            } elseif ($deal->status === 'Following') {
                $totalFollowing += $dealTotal;
            } elseif ($deal->status === 'Forecast') {
                $totalForecast += $dealTotal;
            }
        }

        arsort($courseRevenueMap);
        $courseLabels = array_keys($courseRevenueMap);
        $courseData = array_values($courseRevenueMap);

        uasort($closedSaleCoursesMap, function($a, $b) { return $b['total_revenue'] <=> $a['total_revenue']; });
        uasort($followingCoursesMap, function($a, $b) { return $b['total_revenue'] <=> $a['total_revenue']; });
        uasort($forecastCoursesMap, function($a, $b) { return $b['total_revenue'] <=> $a['total_revenue']; });

        $closedSaleCourses = collect($closedSaleCoursesMap)->map(function($data) {
            return (object)[
                'course' => (object)['course_name' => $data['course_name']],
                'total_person_sum' => $data['total_person'],
                'total_revenue_sum' => $data['total_revenue']
            ];
        })->values();

        $followingCourses = collect($followingCoursesMap)->map(function($data) {
            return (object)[
                'course' => (object)['course_name' => $data['course_name']],
                'total_person_sum' => $data['total_person'],
                'total_revenue_sum' => $data['total_revenue']
            ];
        })->values();

        $forecastCourses = collect($forecastCoursesMap)->map(function($data) {
            return (object)[
                'course' => (object)['course_name' => $data['course_name']],
                'total_person_sum' => $data['total_person'],
                'total_revenue_sum' => $data['total_revenue']
            ];
        })->values();

        $totalNet = $totalClosed / 1.07;
        $totalVat = $totalClosed - $totalNet;

        $availableYears = SalesDeal::selectRaw('YEAR(deal_date) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        if ($availableYears->isEmpty()) {
            $availableYears = collect([date('Y')]);
        }

        return view('dashboard.sales', compact(
            'totalClosed',
            'totalFollowing',
            'totalForecast',
            'chartData',
            'totalNet',
            'totalVat',
            'availableYears',
            'selectedYear',
            'selectedMonth',
            'courseLabels',
            'courseData',
            'selectedStatus',
            'closedSaleCourses',
            'followingCourses',
            'forecastCourses'
        ));
    }
}