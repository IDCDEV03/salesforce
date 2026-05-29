<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบสรุปยอดขาย - {{ $deal->customer->company_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background-color: #ffffff; }
            .print-border { border: 1px solid #cbd5e1 !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen py-8 text-slate-800 antialiased">

    <div class="no-print max-w-4xl mx-auto mb-6 flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <p class="text-sm text-slate-500 font-medium">มุมมองตัวอย่างใบสรุปยอดขายภายใน (Internal Sales Summary)</p>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow transition-all flex items-center gap-1.5 cursor-pointer">
                🖨️ สั่งพิมพ์ / บันทึกเป็น PDF
            </button>
            <button onclick="window.close()" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-semibold transition-all cursor-pointer">
                ปิดหน้านี้
            </button>
        </div>
    </div>

    <div class="max-w-4xl mx-auto bg-white p-10 rounded-none md:rounded-xl shadow-md border border-slate-200 print-border min-h-[297mm]">
        
        <div class="flex justify-between items-start border-b-2 border-slate-900 pb-6 mb-8">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">บริษัท ไอดีไดรฟ์ จํากัด (สำนักงานใหญ่)</h1>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                    บริษัท ไอดีไดรฟ์ จำกัด 200/222 หมู่ 2 ถ.ชัยพฤกษ์ ต.ในเมือง อ.เมือง จ.ขอนแก่น 40000<br>
                    โทรศัพท์: 061-9579956 | อีเมล: ยังไม่รู้
                </p>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-bold text-emerald-600 tracking-wide uppercase">ใบสรุปยอดขาย</h2>
                <span class="text-xs font-semibold text-slate-400 block tracking-widest mt-0.5 uppercase">SALES SUMMARY</span>
                <div class="text-xs text-slate-600 mt-3 space-y-0.5">
                    <p><span class="font-bold">เลขที่รายการขาย:</span> SS-{{ str_pad($deal->id, 5, '0', STR_PAD_LEFT) }}</p>
                    <p><span class="font-bold">วันที่บันทึก:</span> {{ \Carbon\Carbon::parse($deal->deal_date)->format('d/m/Y') }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 bg-slate-50 p-4 rounded-lg border border-slate-100 mb-8">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block mb-1">ข้อมูลลูกค้าและบริษัท / Customer Info</span>
                <h3 class="text-base font-bold text-slate-800">{{ $deal->customer->company_name }}</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                    หมวดหมู่ธุรกิจ: {{ $deal->category ?? 'ทั่วไป' }}<br>
                    ประเภทโครงการ: {{ $deal->group ?? 'ไม่ระบุ' }}
                </p>
            </div>
            <div class="text-right flex flex-col justify-end text-xs text-slate-500 space-y-0.5">
                <p><span class="font-bold text-slate-700">พนักงานผู้ดูแล:</span> {{ Auth::user()->name ?? 'เจ้าหน้าที่ฝ่ายขาย' }}</p>
                <p><span class="font-bold text-slate-700">สถานะงานปัจจุบัน:</span> {{ $deal->status }}</p>
            </div>
        </div>

        <div class="mb-8">
            <table class="w-full text-left border-collapse border border-slate-200">
                <thead>
                    <tr class="bg-slate-900 text-white text-xs uppercase tracking-wider font-semibold">
                        <th class="p-3 border border-slate-200 text-center w-12">ลำดับ</th>
                        <th class="p-3 border border-slate-200">รายการคอร์ส / รายละเอียดสินค้า</th>
                        <th class="p-3 border border-slate-200 text-right w-28">ราคาต่อหน่วย</th>
                        <th class="p-3 border border-slate-200 text-right w-24">ส่วนลด / คน</th>
                        <th class="p-3 border border-slate-200 text-center w-20">จำนวนคน</th>
                        <th class="p-3 border border-slate-200 text-right w-32">จำนวนเงิน (บาท)</th>
                    </tr>
                </thead>
                <tbody class="text-xs text-slate-700 divide-y divide-slate-200">
                    @php $grandTotal = 0; @endphp
                    @forelse($deal->dealItems as $index => $item)
                        @php 
                            $itemDiscount = $item->discount ?? $item->discount_per_person ?? 0;
                            $itemTotal = ($item->price_per_person - $itemDiscount) * $item->total_person;
                            if($itemTotal < 0) $itemTotal = 0;
                            $grandTotal += $itemTotal; 
                        @endphp
                        <tr>
                            <td class="p-3 border border-slate-200 text-center font-medium">{{ $index + 1 }}</td>
                            <td class="p-3 border border-slate-200 font-semibold text-slate-900">{{ $item->course->course_name ?? 'ไม่ระบุชื่อคอร์สเรียน' }}</td>
                            <td class="p-3 border border-slate-200 text-right">฿{{ number_format($item->price_per_person, 2) }}</td>
                            <td class="p-3 border border-slate-200 text-right text-red-600">-฿{{ number_format($itemDiscount, 2) }}</td>
                            <td class="p-3 border border-slate-200 text-center font-medium">{{ number_format($item->total_person) }} คน</td>
                            <td class="p-3 border border-slate-200 text-right font-bold text-slate-900">฿{{ number_format($itemTotal, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400 italic">ไม่มีรายการขายคอร์สเรียนย่อยในเอกสารฉบับนี้</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex justify-between items-start gap-4 mb-16">
            <div class="w-1/2 p-3 bg-slate-50 rounded-lg border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider">หมายเหตุและข้อตกลงภายใน / Internal Notes</span>
                <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                    1. เอกสารฉบับนี้ใช้สำหรับสรุปยอดงานขายภายในแผนกขายเท่านั้น ห้ามใช้อ้างอิงภายนอกองค์กร<br>
                    2. โปรโมชั่นและส่วนลดพิเศษ: {{ $deal->promotion ?? 'ไม่มีโปรโมชั่นเงื่อนไขเพิ่มเติม' }}<br>
                    @if($deal->note) 3. โน้ตเพิ่มเติม: {{ $deal->note }} @endif
                </p>
            </div>
            <div class="w-1/2 flex flex-col items-end">
                <div class="w-full max-w-xs space-y-1.5 text-xs">
                    <div class="flex justify-between text-slate-500">
                        <span>รวมเป็นเงินสุทธิ / Subtotal:</span>
                        <span>฿{{ number_format($grandTotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>ภาษีมูลค่าเพิ่ม / VAT (0%):</span>
                        <span>฿0.00</span>
                    </div>
                    <div class="flex justify-between text-base font-black text-slate-900 border-t-2 border-slate-200 pt-2 mt-2">
                        <span>ยอดรวมทั้งสิ้น / Grand Total:</span>
                        <span class="text-emerald-600">฿{{ number_format($grandTotal, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-12 text-xs pt-8 border-t border-dashed border-slate-200">
            <div class="text-center">
                <p class="text-slate-400 mb-16">ลงชื่อ..........................................................<br>เจ้าหน้าที่ผู้บันทึก / Sales Representative</p>
                <p class="font-bold text-slate-700">วันที่ ......../......../........</p>
            </div>
            <div class="text-center">
                <p class="text-slate-400 mb-16">ลงชื่อ..........................................................<br>ผู้ตรวจสอบแผนกขาย / Verified By</p>
                <p class="font-bold text-slate-700">วันที่ ......../......../........</p>
            </div>
        </div>

    </div>

</body>
</html>