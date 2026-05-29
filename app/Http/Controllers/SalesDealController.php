<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesDeal;
use App\Models\Customer;
use App\Models\Course;
use App\Models\DealItem;
use App\Models\User;
use App\Models\DealLog; // 🟢 เพิ่ม Model สำหรับบันทึกประวัติ
use Illuminate\Support\Facades\Auth;

class SalesDealController extends Controller
{
    // หน้าแสดงรายการดีลงานขายทั้งหมด
    public function index(Request $request)
    {
        $status = $request->get('status');
        $selectedSalesPerson = $request->get('sales_person_id');
        
        // 🟢 รับค่าตัวกรองเดือนและปี
        $selectedMonth = $request->get('month');
        $selectedYear = $request->get('year');

        // ดึงข้อมูลดีลพร้อมข้อมูลลูกค้าและสินค้าที่เชื่อมโยงกัน
        $query = SalesDeal::with(['customer', 'dealItems.course']);

        // 🔒 ระบบล็อกสิทธิ์คัดแยกมุมมองข้อมูลดีลงานขาย (อัปเดตเพิ่มสิทธิ์ให้ Manager เห็นเหมือน Admin)
        $isUserAdminOrManager = Auth::check() && (Auth::user()->isAdmin() || strtolower(Auth::user()->role) === 'manager');

        if ($isUserAdminOrManager) {
            // สิทธิ์ Admin และ Manager: ถ้าเลือกพนักงานจากกล่อง Dropdown ให้กรองเฉพาะดีลของพนักงานคนนั้น ถ้าไม่มีเลือกให้แสดงทั้งหมด
            if ($request->filled('sales_person_id')) {
                $query->where('user_id', $selectedSalesPerson);
            }
        } else {
            // สิทธิ์ Sales ทั่วไป: ล็อกผลลัพธ์ให้มองเห็นเฉพาะดีลงานขายที่เป็นของตนเอง 100% เสมอ
            $query->where('user_id', Auth::id());
        }

        // 🟢 เพิ่มการกรองตามเดือนที่สร้างดีล
        if ($selectedMonth) {
            $query->whereMonth('created_at', $selectedMonth);
        }

        // 🟢 เพิ่มการกรองตามปีที่สร้างดีล
        if ($selectedYear) {
            $query->whereYear('created_at', $selectedYear);
        }

        // กรองตามสถานะดีลที่มีการกดลิงก์แท็บมาจากหน้าบ้าน (ถ้ามี)
        $deals = $query->when($status, function($query, $status) {
                return $query->where('status', $status);
            })
            ->latest()
            ->paginate(15);

        // ดึงรายชื่อพนักงานทั้งหมดส่งไปให้ Admin และ Manager เลือกกรองในหน้า View
        $salesPersons = User::all();

        // 🔔 เช็คงานค้าง (Following, Forecast) ของผู้ใช้งานปัจจุบัน หรือของทุกคนกรณีเป็น Admin/Manager เพื่อนำไปทำแจ้งเตือน
        // โดยใช้เงื่อนไขตรวจสอบ status รูปแบบตัวอักษรเล็ก/ใหญ่ให้ครอบคลุม
        $pendingDealsCount = 0;
        if (Auth::check()) {
            $pendingQuery = SalesDeal::whereIn('status', ['following', 'Following', 'forecast', 'Forecast']);
            
            // ถ้าไม่ใช่ Admin หรือ Manager ให้คัดกรองเฉพาะงานของตัวเอง
            if (!$isUserAdminOrManager) {
                $pendingQuery->where('user_id', Auth::id());
            }
            
            $pendingDealsCount = $pendingQuery->count();
        }

        // 🟢 ส่งค่าทั้งหมด (รวมถึง $pendingDealsCount) กลับไปแสดงผลที่ View
        return view('deals.index', compact('deals', 'status', 'salesPersons', 'selectedSalesPerson', 'selectedMonth', 'selectedYear', 'pendingDealsCount'));
    }

    // หน้าฟอร์มสร้างดีลงานขายใหม่
    public function create()
    {
        $customers = Customer::all();
        $courses = Course::all(); // ดึงรายชื่อคอร์สไปให้เลือกใน Dropdown
        return view('deals.create', compact('customers', 'courses'));
    }

    // บันทึกดีลและไอเท็มสินค้าลงฐานข้อมูล
    public function store(Request $request)
    {
        // ตรวจสอบข้อมูล (เอา course_id, price_per_person, total_person ออกเพราะเราแยกไปทำอีกหน้าแล้ว)
        $request->validate([
            'customer_id'      => 'required',
            'deal_date'        => 'nullable',
            'status'           => 'required|string',
        ]);

        // 1. บันทึกข้อมูลดีลหลัก
        // ตรวจสอบ user_id ให้ปลอดภัย ถ้าล็อกอินให้ใช้ ID คนนั้น ถ้าไม่มีตรวจสอบในตาราง users เสมอ
        $userId = Auth::check() ? Auth::id() : 1;

        // ดักจับ: หากในฟอร์มไม่มีการเลือกวันที่มา ให้ดึงวันที่ปัจจุบัน (Y-m-d) ไปใช้งานเพื่อไม่ให้เกิด Errorในฐานข้อมูล
        $dealDate = $request->deal_date ?: now()->format('Y-m-d');

        $deal = SalesDeal::create([
            'user_id'       => $userId, 
            'customer_id'   => $request->customer_id,
            'deal_date'     => $dealDate,
            'group'         => $request->group,
            'category'      => $request->category,
            'tools'         => $request->tools,
            'promotion'     => $request->promotion,
            'status'        => $request->status,
            'progress'      => $request->progress,
            'receipt_no'    => $request->receipt_no,
            'updated_note'  => $request->updated_note,
            'total_revenue' => 0, // กำหนดค่ายอดรวมเริ่มต้นเป็น 0 ก่อน (เดี๋ยวระบบจะบวกเพิ่มตอนใส่คอร์ส)
        ]);

        // เปลี่ยน Redirect ให้เด้งไปที่หน้าจัดการคอร์ส (Items) ของดีลที่เพิ่งสร้างทันที
        return redirect()->route('deals.items', $deal->id)->with('success', 'สร้างดีลงานขายสำเร็จ! กรุณาเพิ่มรายการคอร์สเรียนด้านล่าง');
    }

    // หน้าฟอร์มแก้ไขดีลงานขาย
    public function edit(SalesDeal $deal)
    {
        // 🟢 โหลดข้อมูลประวัติและชื่อคนที่อัปเดตแนบมาด้วย
        $deal->load(['logs.user']);

        $customers = Customer::all();
        // ส่งตัวแปรเดิมไปใช้งานที่ View อย่างครบถ้วนตามหลัก Route Model Binding
        return view('deals.edit', compact('deal', 'customers'));
    }

    // อัปเดตข้อมูลดีลงานขายลงฐานข้อมูล
    public function update(Request $request, SalesDeal $deal)
    {
        // ปรับปรุง Validation ให้ยืดหยุ่นรองรับฟิลด์อัปเดตสถานะใหม่ และไม่ติด Error เรื่อง customer_id (กรณีแสดงเป็น text บนหน้าจอ)
        $request->validate([
            'customer_id'      => 'nullable',
            'deal_date'        => 'nullable',
            'status'           => 'required|string',
            'progress'         => 'nullable|string|max:255', // แก้ไขตรงนี้: ปลดล็อกจาก integer เป็น string เพื่อรองรับข้อความ
        ]);

        // 🟢 1. ดึงสถานะเดิมมาเก็บไว้เทียบก่อนอัปเดต
        $oldStatus = $deal->status;
        $note = $request->updated_note ?? $request->note;

        // ดักจับ: หากไม่มีการส่งวันที่หรือลูกค้ามาใหม่ ให้คงค่าเดิมในฐานข้อมูลไว้
        $dealDate = $request->deal_date ?: $deal->deal_date;
        $customerId = $request->customer_id ?: $deal->customer_id;

        // อัปเดตข้อมูลในดีล (ผสมผสานฟิลด์จากฟอร์มจัดการสถานะใหม่เข้าไปทั้งหมดอย่างสมบูรณ์)
        $deal->update([
            'customer_id'   => $customerId,
            'deal_date'     => $dealDate,
            'group'         => $request->group ?? $deal->group,
            'category'      => $request->category ?? $deal->category,
            'status'        => $request->status,
            'progress'      => $request->progress ?? $deal->progress,
            'receipt_no'    => $request->receipt_no ?? $deal->receipt_no,
            'note'          => $request->updated_note ?? $request->note ?? $deal->note, 
            'updated_note'  => $request->updated_note ?? $request->note ?? $deal->updated_note,
        ]);

        // 🟢 2. บันทึกประวัติ (Log) เมื่อสถานะเปลี่ยน หรือมีการพิมพ์ข้อความโน้ต
        if ($oldStatus != $request->status || !empty($note)) {
            DealLog::create([
                'sales_deal_id' => $deal->id,
                'user_id'       => Auth::id() ?? 1,
                'old_status'    => $oldStatus,
                'new_status'    => $request->status,
                'note'          => $note
            ]);
        }

        return redirect()->route('deals.index')->with('success', 'อัปเดตข้อมูลสถานะดีลงานขายเรียบร้อยแล้ว!');
    }

    // ลบข้อมูลดีลงานขาย
    public function destroy(SalesDeal $deal)
    {
        // 🔒 ดักตรวจสอบสิทธิ์: อนุญาตให้เฉพาะ Admin และ Manager เท่านั้นที่สามารถลบข้อมูลนี้ได้
        if (!Auth::user()->isAdmin() && strtolower(Auth::user()->role) !== 'manager') {
            return redirect()->route('deals.index')->with('error', '⚠️ คุณไม่มีสิทธิ์ในการลบข้อมูลดีลงานขายนี้กลุ่มผู้ใช้งานของคุณถูกจำกัดสิทธิ์!');
        }

        // ลบข้อมูลไอเท็มที่ผูกกับดีลนี้ก่อนเพื่อไม่ให้ติด Constraint
        $deal->dealItems()->delete();
        
        // ลบตัวดีลหลัก
        $deal->delete();

        return redirect()->route('deals.index')->with('success', 'ลบข้อมูลดีลงานขายออกจากระบบแล้ว!');
    }

    // ฟังก์ชันสำหรับบันทึกคอร์สใหม่ผ่าน AJAX (แก้ไขคีย์ให้ตรงตามโครงสร้างตารางจริงของคุณ)
    public function quickStoreCourse(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:courses,course_name'
        ]);

        $course = Course::create([
            'course_name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'id' => $course->id,
            'name' => $course->course_name
        ]);
    }

    // ==========================================
    // ส่วนที่เพิ่มใหม่สำหรับระบบจัดการ คอร์ส (CRUD)
    // ==========================================

    // แสดงรายชื่อคอร์สทั้งหมดที่มีในระบบ
    public function indexCourse()
    {
        $courses = Course::latest()->get();
        return view('courses.index', compact('courses'));
    }

    // หน้าฟอร์มสำหรับแก้ไขชื่อคอร์ส
    public function editCourse(Course $course)
    {
        return view('courses.edit', compact('course'));
    }

    // บันทึกการแก้ไขชื่อคอร์สลงฐานข้อมูล (ปรับปรุงให้รองรับ Redirect และฟิลด์ใหม่ทั้งหมด)
    public function updateCourse(Request $request, Course $course)
    {
        $request->validate([
            'course_name' => 'required|string|unique:courses,course_name,' . $course->id,
            'description' => 'nullable|string',
            'default_price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean'
        ]);

        $course->update([
            'course_name' => $request->course_name,
            'description' => $request->description,
            'default_price' => $request->default_price,
            'is_active' => $request->has('is_active') ? $request->is_active : true
        ]);

        // รองรับกรณีเรียกผ่าน AJAX
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'id' => $course->id,
                'course_name' => $course->course_name
            ]);
        }

        // กรณีเรียกผ่านแบบฟอร์มหน้าเว็บปกติ ให้ Redirect กลับพร้อมข้อความ
        return redirect()->route('courses.index')->with('success', 'อัปเดตข้อมูลคอร์สเรียนเรียบร้อยแล้ว!');
    }

    // ลบคอร์สออกจากระบบ (ปรับปรุงให้รองรับ Redirect กลับไปหน้าเดิมเมื่อลบสำเร็จหรือติดเงื่อนไข)
    public function destroyCourse(Course $course)
    {
        // ตรวจสอบก่อนว่าคอร์สนี้ถูกนำไปใช้ในดีลขายใด ๆ หรือไม่เพื่อความปลอดภัย
        if ($course->dealItems()->exists()) {
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'ไม่สามารถลบคอร์สนี้ได้ เนื่องจากมีข้อมูลใช้งานอยู่ในดีลงานขาย!'
                ], 422);
            }
            return redirect()->route('courses.index')->with('error', 'ไม่สามารถลบคอร์สนี้ได้ เนื่องจากมีข้อมูลใช้งานอยู่ในดีลงานขาย!');
        }

        $course->delete();
        
        if (request()->wantsJson()) {
            return response()->json([
                'success' => true
            ]);
        }

        return redirect()->route('courses.index')->with('success', 'ลบคอร์สเรียนออกจากฐานข้อมูลเรียบร้อยแล้ว!');
    }

    // ==========================================
    // ส่วนที่เพิ่มใหม่สำหรับจัดการคอร์สย่อย (Deal Items) หลายรายการ
    // ==========================================

    // หน้าจอสำหรับจัดการรายการคอร์สเรียนย่อยที่ผูกกับดีลหลักนี้
    public function manageItems($id)
    {
        // ดึงข้อมูลดีลหลัก พร้อมลูกคัา และไอเท็มคอร์สที่มีอยู่แล้วโหลดประวัติ activityLogs พ่วงไปด้วย
        $deal = SalesDeal::with(['customer', 'dealItems.course', 'activityLogs.user'])->findOrFail($id);
        
        // ดึงรายชื่อคอร์สทั้งหมดจากฐานข้อมูล เพื่อให้เลือกใน Dropdown
        $courses = Course::all();
        $courseOptions = $courses; // เพิ่มตัวแปรนี้เพื่อแก้ปัญหา Undefined variable ในหน้า View

        return view('deals.items', compact('deal', 'courses', 'courseOptions'));
    }

    // บันทึกรายการคอร์สย่อยเข้าฐานข้อมูล และคำนวณเงินรวมอัตโนมัติ
    public function storeItem(Request $request, $id)
    {
        // ปรับปรุง Validation ให้รองรับชื่อตัวแปรส่วนลดทั้ง 2 รูปแบบที่มีโอกาสส่งมาจากฟอร์มหน้าบ้าน
        $request->validate([
            'course_id'           => 'required',
            'price_per_person'    => 'required|numeric|min:0',
            'discount'            => 'nullable|numeric|min:0',
            'discount_per_person' => 'nullable|numeric|min:0',
            'total_person'        => 'required|integer|min:1',
        ]);

        // ดักรับค่าส่วนลด: ไม่ว่าหน้าบ้านจะตั้งชื่อ input ว่า discount หรือ discount_per_person ระบบจะดึงมาคำนวณได้ถูกต้อง
        $discount = $request->discount ?? $request->discount_per_person ?? 0;

        // คำนวณยอดรวมของคอร์สนี้โดยอัตโนมัติ ((ราคาต่อคน - ส่วนลดต่อคน) x จำนวนคน)
        $totalRevenue = ($request->price_per_person - $discount) * $request->total_person;

        // ป้องกันกรณีใส่ส่วนลดเยอะกว่าราคาจนยอดรวมติดลบ
        if ($totalRevenue < 0) {
            $totalRevenue = 0;
        }

        // บันทึกลงตารางย่อย deal_items
        DealItem::create([
            'sales_deal_id'       => $id,
            'course_id'           => $request->course_id,
            'price_per_person'    => $request->price_per_person,
            'discount'            => $discount, // แก้ไขตรงนี้ให้ตรงกับคอลัมน์ในฐานข้อมูล!
            'total_person'        => $request->total_person,
            'total_revenue'       => $totalRevenue,
        ]);

        // คำนวณยอดรวมคอร์สย่อยทั้งหมด แล้วนำไปอัปเดตลงดีลหลัก (sales_deals)
        $mainDeal = SalesDeal::findOrFail($id);
        $grandTotalItems = DealItem::where('sales_deal_id', $id)->sum('total_revenue');
        $mainDeal->update([
            'total_revenue' => $grandTotalItems
        ]);

        return redirect()->route('deals.items', $id)->with('success', 'เพิ่มรายการคอร์สเรียนเข้าไปในดีลนี้และอัปเดตยอดเงินรวมเรียบร้อยแล้ว!');
    }

    /**
     * ฟังก์ชันเพิ่มเติมสำหรับรองรับ Route ชื่อ deals.items
     * เพื่อดึงข้อมูลเข้าสู่หน้า deals.items ได้อย่างสมบูรณ์แบบ
     */
    public function items($id)
    {
        // โหลดข้อมูลดีลพ่วงข้อมูลประวัติความสัมพันธ์ activityLogs.user เข้าไปด้วยเพื่อส่งไปแสดงผลที่ View หน้าจัดการดีลได้
        $deal = SalesDeal::with(['customer', 'dealItems.course', 'activityLogs.user'])->findOrFail($id);
        $courses = Course::all();
        $courseOptions = $courses; // เพิ่มตัวแปรนี้เพื่อแก้ปัญหา Undefined variable ในหน้า View

        return view('deals.items', compact('deal', 'courses', 'courseOptions'));
    }

    /**
     * ฟังก์ชันสำหรับลบรายการคอร์สเรียนย่อยออกจากดีล (เพิ่มใหม่สำหรับระบบลบไอเท็ม)
     */
    public function destroyItem($id)
    {
        $item = DealItem::findOrFail($id);
        $dealId = $item->sales_deal_id; // เก็บ ID ดีลหลักไว้สำหรับ redirect กลับ
        
        $item->delete(); // สั่งลบแถวคอร์สย่อยออกจากตาราง deal_items

        // หลังลบเสร็จ คำนวณยอดเงินรวมของไอเท็มที่เหลืออยู่ใหม่ทั้งหมด แล้วนำไปอัปเดตลงดีลหลัก
        $mainDeal = SalesDeal::findOrFail($dealId);
        $grandTotalItems = DealItem::where('sales_deal_id', $dealId)->sum('total_revenue');
        $mainDeal->update([
            'total_revenue' => $grandTotalItems
        ]);

        return redirect()->route('deals.items', $dealId)->with('success', 'ลบรายการคอร์สเรียนออกจากดีลและคำนวณยอดเงินรวมใหม่เรียบร้อยแล้ว!');
    }

    /**
     * 🌟 ฟังก์ชันเปิดหน้าพิมพ์ใบเสนอราคา (Quotation Layout)
     */
    public function printQuotation($id)
    {
        $deal = SalesDeal::with(['customer', 'dealItems.course'])->findOrFail($id);
        return view('deals.quotation', compact('deal'));
    }
}