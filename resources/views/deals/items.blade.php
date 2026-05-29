@extends('layouts.app')

@section('page_title', 'จัดการรายการคอร์สเรียนในการขาย')

@section('content')
<div class="space-y-6">

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-md font-medium">ข้อมูลการขายหลัก</span>
            <div class="flex items-center gap-2">
                <a href="{{ route('deals.quotation', $deal->id) }}" target="_blank" class="text-sm bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg font-medium transition-colors shadow-sm">
                    🖨️ พิมพ์ใบสรุปยอดขาย
                </a>
                <a href="{{ route('sales_deals.index') }}" class="text-sm text-slate-500 hover:text-slate-800">⬅️ กลับหน้าตารางรวม</a>
            </div>
        </div>
        <h3 class="text-lg font-bold text-gray-900">🏢 บริษัท: {{ $deal->customer->company_name }}</h3>
        <p class="text-gray-500 text-sm mt-1">
            วันที่: {{ \Carbon\Carbon::parse($deal->deal_date)->format('d/m/Y') }} | 
            หมวดหมู่: <span class="font-medium text-slate-700">{{ $deal->category }}</span> | 
            App | สถานะปัจจุบัน: <span class="font-bold text-emerald-600">{{ $deal->status }}</span>
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 h-fit">
            <h4 class="font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4">➕ เพิ่มรายการคอร์สเรียน</h4>
            
            <form action="{{ route('sales_deals.items.store', $deal->id) }}" method="POST" class="space-y-4" id="deal-item-form">
                @csrf
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">เลือกชื่อคอร์ส/ผลิตภัณฑ์</label>
                    <select name="course_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-slate-500" required>
                        <option value="">-- กรุณาเลือกคอร์ส --</option>
                        @foreach($courseOptions as $course)
                            <option value="{{ $course->id }}">{{ $course->course_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">ราคาขายต่อคน (บาท)</label>
                    <input type="number" name="price_per_person" id="price_per_person" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-slate-500" placeholder="0.00" min="0" required>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">ส่วนลด / คน (บาท)</label>
                    <input type="number" name="discount" id="discount_per_person" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-slate-500" placeholder="0.00" min="0" value="0" required>
                    <input type="hidden" name="discount_per_person" id="hidden_discount_alt" value="0">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">จำนวนคน (Person)</label>
                    <input type="number" name="total_person" id="total_person" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-slate-500" placeholder="0" min="1" required>
                </div>

                <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
                    <span class="text-xs text-gray-400 block font-medium">คำนวณยอดขายรวมของคอร์สนี้:</span>
                    <span class="text-lg font-bold text-slate-800" id="live_total_preview">฿0.00</span>
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2 rounded-lg text-sm transition-colors shadow-sm">
                    บันทึกเพิ่มคอร์ส
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col justify-between">
            <div>
                <div class="p-6 border-b border-gray-100">
                    <h4 class="font-bold text-gray-800">📋 รายการคอร์สที่รวมอยู่ในการขายนี้</h4>
                </div>

                @if(session('success'))
                    <div class="mx-6 mt-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-xs">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                <th class="p-4">ชื่อคอร์สที่ลงเรียน</th>
                                <th class="p-4 text-right">ราคาต่อคน</th>
                                <th class="p-4 text-right">ส่วนลด/คน</th>
                                <th class="p-4 text-center">จำนวนคน</th>
                                <th class="p-4 text-right">ยอดรวมรวม</th>
                                <th class="p-4 text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-gray-600 divide-y divide-gray-50">
                            @php $grandTotal = 0; @endphp
                            @forelse($deal->dealItems as $item)
                            @php 
                                // ป้องกันชื่อคอลัมน์ในฐานข้อมูลสับสน ดึงค่าใดค่าหนึ่งที่ได้มาคำนวณจริงหน้าโรงงาน
                                $itemDiscount = $item->discount ?? $item->discount_per_person ?? 0;
                                $itemTotal = ($item->price_per_person - $itemDiscount) * $item->total_person;
                                if($itemTotal < 0) $itemTotal = 0;
                                $grandTotal += $itemTotal; 
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="p-4 font-semibold text-gray-800">{{ $item->course->course_name ?? 'ไม่ระบุชื่อคอร์ส' }}</td>
                                <td class="p-4 text-right">฿{{ number_format($item->price_per_person, 2) }}</td>
                                <td class="p-4 text-right text-red-500">-฿{{ number_format($itemDiscount, 2) }}</td>
                                <td class="p-4 text-center font-medium">{{ $item->total_person }} คน</td>
                                <td class="p-4 text-right font-bold text-slate-700">฿{{ number_format($itemTotal, 2) }}</td>
                                <td class="p-4 text-center">
                                    <form action="{{ route('sales_deals.items.destroy', $item->id) }}" method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบรายการคอร์สนี้ออกจากการขาย?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 p-1 rounded transition-colors" title="ลบคอร์สนี้">
                                            🗑️ ลบ
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-12 text-gray-400">ยังไม่มีการเพิ่มคอร์สเรียนย่อยในการขายนี้ เริ่มเพิ่มได้จากฟอร์มด้านซ้ายเลยครับ</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($deal->dealItems && $deal->dealItems->count() > 0)
            <div class="bg-slate-800 p-4 flex items-center justify-between text-white">
                <span class="text-sm font-medium text-slate-400">ยอดรวมสถิติการขายนี้ทั้งหมด (ยังไม่หักส่วนลดหลัก):</span>
                <span class="text-xl font-black">฿{{ number_format($grandTotal, 2) }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-slate-50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-history text-slate-500"></i> ประวัติการอัปเดตและกิจกรรมในการขายนี้
            </h3>
            <span class="text-xs bg-slate-200 text-slate-700 px-2.5 py-1 rounded-full font-medium">
                ทั้งหมด {{ $deal->activityLogs->count() }} รายการ
            </span>
        </div>
        
        <div class="p-6 max-h-[400px] overflow-y-auto bg-slate-50/30">
            @if($deal->activityLogs->count() > 0)
                <div class="relative border-l-2 border-slate-200 ml-3 space-y-6 py-2">
                    @foreach($deal->activityLogs as $log)
                        <div class="relative pl-6">
                            <span class="absolute -left-[7px] top-1.5 bg-white rounded-full p-0.5 flex items-center justify-center">
                                @if($log->action == 'Created')
                                    <span class="h-3 w-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
                                @elseif($log->action == 'Deleted')
                                    <span class="h-3 w-3 rounded-full bg-rose-500 ring-4 ring-rose-100"></span>
                                @else
                                    <span class="h-3 w-3 rounded-full bg-amber-500 ring-4 ring-amber-100"></span>
                                @endif
                            </span>

                            <div class="bg-white p-4 rounded-lg border border-gray-100 shadow-2xs hover:shadow-xs transition duration-150">
                                <div class="flex justify-between items-start flex-wrap gap-2 mb-1.5">
                                    <span class="text-sm font-semibold text-slate-800">
                                        {{ $log->description }}
                                    </span>
                                    <span class="text-xs text-gray-400 flex items-center gap-1">
                                        <i class="fa-regular fa-clock"></i> {{ $log->created_at->locale('th')->diffForHumans() }} 
                                        ({{ $log->created_at->format('d/m/Y H:i') }} น.)
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <i class="fa-solid fa-user-circle text-slate-400"></i>
                                    <span>ผู้บันทึก: <strong>{{ $log->user->name ?? 'ระบบอัตโนมัติ/พนักงาน' }}</strong></span>
                                    <span class="text-gray-300">|</span>
                                    <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider">
                                        {{ $log->action }}
                                    </span>
                                </div>

                                @if($log->action == 'Updated' && !empty($log->new_values))
                                    <div class="mt-2.5 pt-2 border-t border-dashed border-gray-100 text-xs text-slate-500 space-y-1 bg-slate-50/50 p-2 rounded">
                                        <span class="font-medium text-slate-600 block mb-1"><i class="fa-solid fa-magnifying-glass"></i> รายละเอียดการเปลี่ยนแปลง:</span>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                                            @foreach($log->new_values as $key => $newValue)
                                                @if(in_array($key, ['status', 'progress', 'receipt_no', 'updated_note']))
                                                    <div class="truncate">
                                                        <span class="text-gray-400">{{ __($key) != $key ? __($key) : $key }}:</span> 
                                                        <span class="text-rose-500 line-through font-mono">{{ $log->old_values[$key] ?? 'ว่าง' }}</span> 
                                                        <i class="fa-solid fa-arrow-right text-[10px] mx-1 text-gray-400"></i>
                                                        <span class="text-emerald-600 font-semibold font-mono">{{ $newValue ?? 'ว่าง' }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-gray-400 bg-white rounded-lg border border-gray-100">
                    <i class="fa-solid fa-folder-open text-2xl mb-2 text-gray-300 block"></i>
                    ยังไม่มีบันทึกประวัติกิจกรรมสำหรับการขายนี้ เนื่องจากเป็นการขายเก่าก่อนเริ่มระบบล็อกประวัติ
                </div>
            @endif
        </div>
    </div>

</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const priceInput = document.getElementById('price_per_person');
        const discountInput = document.getElementById('discount_per_person');
        const hiddenDiscountAlt = document.getElementById('hidden_discount_alt');
        const personInput = document.getElementById('total_person');
        const livePreview = document.getElementById('live_total_preview');

        function calculateLiveTotal() {
            const price = parseFloat(priceInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            const person = parseInt(personInput.value) || 0;
            
            // คัดลอกค่าส่วนลดไปยังอินพุตสำรองเพื่อให้ส่งค่าไปทั้งคู่ ป้องกัน Controller ตรวจจับพลาดชื่อฟิลด์
            if(hiddenDiscountAlt) {
                hiddenDiscountAlt.value = discount;
            }

            // คำนวณ (ราคา - ส่วนลด) * จำนวนคน
            let total = (price - discount) * person;
            if (total < 0) total = 0; 

            livePreview.innerText = '฿' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        priceInput.addEventListener('input', calculateLiveTotal);
        discountInput.addEventListener('input', calculateLiveTotal);
        personInput.addEventListener('input', calculateLiveTotal);
    });
</script>
@endsection