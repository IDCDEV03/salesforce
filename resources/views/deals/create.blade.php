@extends('layouts.app')

@section('page_title', 'บันทึกการขายงานใหม่')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <a href="{{ route('deals.index') }}" class="inline-flex items-center text-sm font-medium text-slate-600 hover:text-slate-900 transition-colors">
            <i class="fa-solid fa-arrow-left-long mr-2"></i> กลับไปหน้ารายการงานขาย
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6 border-b border-gray-100 bg-slate-50">
            <h3 class="text-base font-bold text-gray-800">สร้างการขายใหม่ (Deal Information)</h3>
            <p class="text-gray-500 text-xs mt-1">กรอกข้อมูลบริษัทลูกค้าและสถานะการขาย (สามารถเพิ่มคอร์สและสินค้าได้ในขั้นตอนถัดไป)</p>
        </div>

        @if ($errors->any())
            <div class="p-4 mx-6 mt-4 bg-rose-50 border-l-4 border-rose-500 text-rose-700 text-sm rounded-r-lg">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('deals.store') }}" method="POST" class="p-6 space-y-5">
            @csrf

            <input type="hidden" name="user_id" value="{{ auth()->id() ?? 1 }}">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-gray-700 mb-1">เลือกบริษัทคู่ค้า / ลูกค้า <span class="text-rose-500">*</span></label>
                    <select name="customer_id" id="customer_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-white">
                        <option value="">-- กรุณาเลือกบริษัทลูกค้า --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->company_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="deal_date" class="block text-sm font-medium text-gray-700 mb-1">วันที่บันทึกการขาย <span class="text-rose-500">*</span></label>
                    <input type="date" name="deal_date" id="deal_date" required value="{{ old('deal_date', date('Y-m-d')) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">สถานะการขาย (Status) <span class="text-rose-500">*</span></label>
                    <select name="status" id="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-white">
                        <option value="Forecast" {{ old('status') == 'Forecast' ? 'selected' : '' }}>Forecast (ประมาณการยอดขาย)</option>
                        <option value="Following" {{ old('status') == 'Following' ? 'selected' : '' }}>Following (กำลังติดตามงาน)</option>
                        <option value="Closed Sale" {{ old('status') == 'Closed Sale' ? 'selected' : '' }}>Closed Sale (ปิดการขายสำเร็จ)</option>
                        <option value="Denied" {{ old('status') == 'Denied' ? 'selected' : '' }}>Denied (ปฏิเสธ/ยกเลิก)</option>
                    </select>
                </div>

                <div>
                    <label for="progress" class="block text-sm font-medium text-gray-700 mb-1">Progress (สถานะย่อย)</label>
                    <input type="text" name="progress" id="progress" list="progress_list" value="{{ old('progress') }}" placeholder="ส่งใบเสนอราคาแล้ว / รอสัญญา" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <datalist id="progress_list">
                        <option value="ส่งใบเสนอราคาแล้ว (Waiting Quoted)">
                        <option value="กำลังพิจารณาสัญญา (Reviewing Contract)">
                        <option value="รอโอนเงินมัดจำ">
                        <option value="ติดตามงานครั้งที่ 1">
                    </datalist>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label for="group" class="block text-sm font-medium text-gray-700 mb-1">Group</label>
                    <input type="text" name="group" id="group" list="group_list" value="{{ old('group') }}" placeholder="เช่น Corporate" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <datalist id="group_list">
                        <option value="Corporate">
                        <option value="Person">
                        <option value="Government">
                    </datalist>
                </div>
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <input type="text" name="category" id="category" list="category_list" value="{{ old('category') }}" placeholder="เช่น SME" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <datalist id="category_list">
                        <option value="SME">
                        <option value="Public">
                        <option value="In-house">
                    </datalist>
                </div>
                <div>
                    <label for="tools" class="block text-sm font-medium text-gray-700 mb-1">Tools</label>
                    <input type="text" name="tools" id="tools" list="tools_list" value="{{ old('tools') }}" placeholder="เช่น Sales" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    <datalist id="tools_list">
                        <option value="Sales">
                        <option value="Line OA">
                        <option value="Facebook Ads">
                        <option value="Cold Call">
                        <option value="Inbound">
                    </datalist>
                </div>
                <div>
                    <label for="promotion" class="block text-sm font-medium text-gray-700 mb-1">Promotion</label>
                    <input type="text" name="promotion" id="promotion" value="{{ old('promotion') }}" placeholder="โปรโมชัน / ส่วนลดพิเศษ" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="receipt_no" class="block text-sm font-medium text-gray-700 mb-1">เลขที่ใบเสร็จ</label>
                    <input type="text" name="receipt_no" id="receipt_no" value="{{ old('receipt_no') }}" placeholder="ระบุเลขที่ใบเสร็จ (ถ้ามี)" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                </div>
            </div>

            <div>
                <label for="updated_note" class="block text-sm font-medium text-gray-700 mb-1">บันทึกเพิ่มเติม (Up-dated Noted)</label>
                <textarea name="updated_note" id="updated_note" rows="3" placeholder="พิมพ์หมายเหตุ ความคืบหน้า หรือรายละเอียดเพิ่มเติมเกี่ยวกับการขายนี้..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">{{ old('updated_note') }}</textarea>
            </div>

            <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                <a href="{{ route('deals.index') }}" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 px-5 py-2 rounded-lg text-sm font-medium transition-colors">
                    ยกเลิก
                </a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
                    <i class="fa-solid fa-arrow-right mr-1"></i> บันทึกและไปเพิ่มคอร์ส
                </button>
            </div>
        </form>
    </div>

</div>
@endsection