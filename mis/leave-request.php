<?php
// Request for Leave Form (RLF) - web input + matching printable HR form.
// Save in your taskmanager folder and adjust the MIS Head / HR integration as needed.
session_start();
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Adjust these session keys if your login code uses different names.
$userRole = strtoupper(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'MISUSER')));
$isMISHead = ($userRole === 'MISHEAD');
$isHRUser = ($userRole === 'HRUSER');

// IMPORTANT: In production, load this value from the saved leave-request record.
// This session value is only a temporary integration placeholder.
$leaveMISDecision = strtoupper((string)($_SESSION['leave_mis_decision'] ?? 'PENDING'));
$misApproved = ($leaveMISDecision === 'APPROVED');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Request for Leave Form (RLF)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--navy:#12345b;--red:#d71920;--line:#222}
body{background:#f2f5f9;color:#172033;font-family:Arial,Helvetica,sans-serif}
.app{max-width:1080px;margin:25px auto;padding:0 14px}.card{border:0;border-radius:14px;box-shadow:0 5px 24px #14213d12}
.card-header{background:var(--navy);color:#fff;border-radius:14px 14px 0 0!important}
.form-label{font-size:13px;font-weight:700}.section-title{font-weight:800;color:var(--navy);border-bottom:1px solid #dbe3ed;padding-bottom:7px;margin:20px 0 12px}
.step{border:1px solid #dbe3ed;border-radius:10px;padding:12px;background:#fff}.step.active{border-color:#2563eb;background:#eff6ff}.step.done{border-color:#16a34a;background:#f0fdf4}
#paper{background:#fff;color:#111;max-width:1050px;margin:22px auto;padding:18px;border:1px solid #bbb}
.print-head{border:1px solid var(--line);background:#e5e5e5;padding:5px 10px;display:flex;justify-content:space-between;font-size:20px;font-weight:bold}
.print-head small{display:block;font-size:12px}.instructions{border:1px solid var(--line);border-top:0;padding:5px 10px;font-size:10px;line-height:1.35}
.print-grid{display:grid;grid-template-columns:46% 25% 29%;border-left:1px solid var(--line)}
.cell{border-right:1px solid var(--line);border-bottom:1px solid var(--line);padding:5px 8px;min-height:41px;font-size:11px;overflow-wrap:anywhere}
.cell b{font-size:11px}.wide{grid-column:1/-1}.span2{grid-column:span 2}.red{color:var(--red);font-weight:bold;white-space:pre-wrap}
.leave-area{grid-column:1/-1;display:grid;grid-template-columns:48% 52%;border-bottom:1px solid var(--line);font-size:11px}
.leave-left,.leave-right{padding:6px 8px}.leave-left{border-right:1px solid var(--line)}
.hr-table{width:100%;border-collapse:collapse;margin-top:5px;font-size:10px}.hr-table td,.hr-table th{border:1px solid #333;padding:4px;text-align:center}
.cert{grid-column:1/-1;border-bottom:1px solid var(--line);padding:7px 9px;font-size:9px;line-height:1.4}
.signature-row,.approval-grid{grid-column:1/-1;display:grid;grid-template-columns:50% 50%;text-align:center}
.signature-row>div,.approval-grid>div{border-bottom:1px solid var(--line);padding:9px 6px;min-height:52px;font-size:10px}
.approval-grid>div:first-child{border-right:1px solid var(--line)}.signspace{height:23px}
.workflow-note{font-size:10px;color:#333;border:1px solid #333;padding:5px;margin-top:5px}
@media print{@page{size:A4 landscape;margin:8mm}body{background:#fff}.no-print{display:none!important}#paper{max-width:none;margin:0;padding:0;border:0}.cell{min-height:37px}}
</style>
</head>
<body>
<div class="app no-print">
 <div class="card">
  <div class="card-header p-3"><h4 class="mb-1">Request for Leave Form (RLF)</h4><small>Complete the online form. Review the HR-form preview and print when ready.</small></div>
  <div class="card-body p-4">
   <div class="alert alert-info small">Signed in role: <b><?=htmlspecialchars($userRole)?></b>. Leave type is a single-choice radio option. Approval and HR controls are shown only to their authorized user levels.</div>
   <div class="row g-2 mb-3">
    <div class="col-md-4"><div class="step active" id="step1"><b>1. Associate Request</b><div class="small text-secondary">Fill out and submit leave details</div></div></div>
    <div class="col-md-4"><div class="step" id="step2"><b>2. MIS Head Approval</b><div class="small text-secondary">Review and approve / disapprove</div></div></div>
    <div class="col-md-4"><div class="step" id="step3"><b>3. HR Unit Processing</b><div class="small text-secondary">Enabled after MIS Head approval</div></div></div>
   </div>
   <form id="rlfForm">
    <h6 class="section-title">Associate Information</h6>
    <div class="row g-3">
     <div class="col-md-4"><label class="form-label">Date Prepared</label><input class="form-control" type="date" name="date_prepared" value="<?=$today?>" required></div>
     <div class="col-md-4"><label class="form-label">Company</label><input class="form-control" name="company" required></div>
     <div class="col-md-4"><label class="form-label">Department / Unit</label><input class="form-control" name="department" required></div>
     <div class="col-md-6"><label class="form-label">Associate's Name</label><input class="form-control" name="associate_name" required></div>
     <div class="col-md-6"><label class="form-label">Designation</label><input class="form-control" name="designation" required></div>
     <div class="col-md-4"><label class="form-label">Employment Status</label><select class="form-select" name="employment_status"><option value="">Select status</option><option>Regular</option><option>Probationary</option><option>Contractual</option><option>Project-based</option></select></div>
     <div class="col-md-8"><label class="form-label">Contact Number During Leave</label><input class="form-control" name="contact_number" placeholder="09XXXXXXXXX"></div>
     <div class="col-12"><label class="form-label">Person(s) Designated to Perform Duties &amp; Responsibilities in Your Absence</label><input class="form-control" name="designated_person"></div>
    </div>
    <h6 class="section-title">Leave Details</h6>
    <div class="row g-3">
     <div class="col-md-5"><label class="form-label">Purpose of Leave</label><input class="form-control" name="purpose" required></div>
     <div class="col-md-4"><label class="form-label">Effective Date</label><input class="form-control" type="date" name="effective_date" required></div>
     <div class="col-md-3"><label class="form-label">Number of Days</label><input class="form-control" type="number" name="days" min=".5" step=".5" required></div>
     <div class="col-md-6"><label class="form-label d-block">Nature of Leave <span class="text-danger">*</span></label>
      <div class="border rounded p-3">
       <?php foreach(['Vacation','Sick','Maternity','Others'] as $n):?><div class="form-check"><input class="form-check-input" type="radio" name="nature" value="<?=$n?>" id="nature<?=$n?>" required><label class="form-check-label" for="nature<?=$n?>"><?=$n?></label></div><?php endforeach;?>
       <input class="form-control form-control-sm mt-2" name="nature_other" id="natureOther" placeholder="Specify if Others" disabled>
      </div>
     </div>
     <div class="col-md-6"><label class="form-label d-block">Payment</label><div class="border rounded p-3">
      <div class="form-check"><input class="form-check-input" type="radio" name="payment" value="With pay" id="withPay"><label class="form-check-label" for="withPay">With pay</label></div>
      <div class="form-check"><input class="form-check-input" type="radio" name="payment" value="Without pay" id="withoutPay"><label class="form-check-label" for="withoutPay">Without pay</label></div>
     </div></div>
    </div>
    <?php if ($isMISHead): ?>
    <h6 class="section-title">MIS Head Approval</h6>
    <div class="row g-3">
     <div class="col-md-4"><label class="form-label">MIS Head Decision</label><select class="form-select" name="mis_decision" id="misDecision"><option value="Pending">Pending Review</option><option value="Approved">Approved</option><option value="Disapproved">Disapproved</option></select></div>
     <div class="col-md-5"><label class="form-label">MIS Head Name</label><input class="form-control" name="mis_head_name" placeholder="Name of MIS Head"></div>
     <div class="col-md-3"><label class="form-label">Decision Date</label><input class="form-control" type="date" name="mis_decision_date"></div>
     <div class="col-12"><label class="form-label">Reason / Remarks (required if disapproved)</label><input class="form-control" name="mis_remarks" id="misRemarks"></div>
    </div>
    <?php elseif (!$isHRUser): ?>
    <div class="alert alert-secondary small mt-3">MIS Head Approval is restricted to MISHEAD accounts.</div>
    <?php endif; ?>

    <?php if ($isHRUser && $misApproved): ?>
     <h6 class="section-title">HR Unit Processing <span class="badge text-bg-success">MIS Head approved</span></h6>
     <div class="row g-3">
      <div class="col-md-3"><label class="form-label">Vacation Leave Credits</label><input class="form-control" type="number" step=".5" name="vacation_credits"></div>
      <div class="col-md-3"><label class="form-label">Sick Leave Credits</label><input class="form-control" type="number" step=".5" name="sick_credits"></div>
      <div class="col-md-3"><label class="form-label">Vacation Leave Less This Leave</label><input class="form-control" type="number" step=".5" name="vacation_less"></div>
      <div class="col-md-3"><label class="form-label">Sick Leave Less This Leave</label><input class="form-control" type="number" step=".5" name="sick_less"></div>
      <div class="col-md-3"><label class="form-label">Vacation Available Balance</label><input class="form-control" type="number" step=".5" name="vacation_balance"></div>
      <div class="col-md-3"><label class="form-label">Sick Available Balance</label><input class="form-control" type="number" step=".5" name="sick_balance"></div>
      <div class="col-md-3"><label class="form-label">Confirmed Available Balance By</label><input class="form-control" name="confirmed_by"></div>
      <div class="col-md-3"><label class="form-label">HR Date Signed</label><input class="form-control" type="date" name="hr_signed"></div>
      <div class="col-md-4"><label class="form-label">HR Action</label><select class="form-select" name="hr_action"><option value="">Pending HR processing</option><option>Approved</option><option>Disapproved</option></select></div>
      <div class="col-md-4"><label class="form-label">HR / Authorized Approver</label><input class="form-control" name="approved_by"></div>
      <div class="col-md-4"><label class="form-label">Final Approval Date</label><input class="form-control" type="date" name="approval_date"></div>
     </div>
    <?php elseif ($isHRUser): ?>
     <div class="alert alert-warning small mt-3">HR Unit Processing is locked. This leave request must first be approved by MIS Head.</div>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mt-4"><button type="button" class="btn btn-primary" id="previewBtn">Update Print Preview</button><button type="button" class="btn btn-success" id="printBtn">Print RLF Form</button><button type="reset" class="btn btn-outline-secondary">Clear Form</button></div>
    <p class="small text-secondary mt-2 mb-0">This version provides the form and print preview. Database saving, authenticated MIS Head approval, and HR workflow must be connected to your existing MIS login and leave-request tables before production use.</p>
   </form>
  </div>
 </div>
</div>

<section id="paper">
 <div class="print-head"><div>REQUEST FOR LEAVE FORM (RLF)<small>No. 012</small></div><span style="font-size:11px;font-weight:normal">HRA Form</span></div>
 <div class="instructions"><b>Instructions:</b><br>1. Accomplish the form in 3 copies. This form must be approved and signed by the immediate superior prior to the actual leave availment.<br>2. Attach necessary supporting documents as needed e.g. medical certificates.<br>3. For documentation purposes, submit 2 copies to the HR Unit; 1-copy for your respective unit file.</div>
 <div class="print-grid">
  <div class="cell"><b>DATE PREPARED:</b> <span class="red" data-out="date_prepared"></span></div><div class="cell"><b>COMPANY:</b> <span class="red" data-out="company"></span></div><div class="cell"><b>DEPARTMENT/UNIT:</b> <span class="red" data-out="department"></span></div>
  <div class="cell span2"><b>ASSOCIATE'S NAME:</b> <span class="red" data-out="associate_name"></span></div><div class="cell"><b>DESIGNATION:</b> <span class="red" data-out="designation"></span></div>
  <div class="cell"><b>EMPLOYMENT STATUS:</b> <span class="red" data-out="employment_status"></span></div><div class="cell span2"><b>CONTACT NUMBER</b> (when on leave in case of official emergency): <span class="red" data-out="contact_number"></span></div>
  <div class="cell wide"><b>PERSON(S) DESIGNATED TO PERFORM THE DUTIES &amp; RESPONSIBILITIES IN MY ABSENCE</b><br><span class="red" data-out="designated_person"></span></div>
  <div class="cell"><b>PURPOSE OF LEAVE:</b><br><span class="red" data-out="purpose"></span></div><div class="cell"><b>EFFECTIVE DATE:</b><br><span class="red" data-out="effective_date"></span></div><div class="cell"><b>NO. OF DAYS:</b><br><span class="red" data-out="days"></span></div>
  <div class="leave-area"><div class="leave-left"><b>NATURE OF LEAVE</b> (Pls. check appropriate box below)<div id="printNature"></div><div id="printPayment"></div></div>
   <div class="leave-right"><b>TO BE FILLED-UP BY THE IMMEDIATE SUPERVISOR/HR UNIT</b><table class="hr-table"><tr><th colspan="2">LEAVE CREDITS to date</th><th>Vacation Leave</th><th>Sick Leave</th></tr><tr><td colspan="2">Credits</td><td class="red" data-out="vacation_credits"></td><td class="red" data-out="sick_credits"></td></tr><tr><td colspan="2">Less: this leave</td><td class="red" data-out="vacation_less"></td><td class="red" data-out="sick_less"></td></tr><tr><th colspan="2">AVAILABLE BALANCE to date</th><td class="red" data-out="vacation_balance"></td><td class="red" data-out="sick_balance"></td></tr></table><div class="mt-2">Confirmed available balance by: <span class="red" data-out="confirmed_by"></span>　Date signed: <span class="red" data-out="hr_signed"></span><div class="signspace"></div><center>Signature over printed name</center></div></div>
  </div>
  <div class="cert"><b>CERTIFICATION:</b> I hereby request leave from my duty as stated above and certify that such leave/absence is requested for the purpose(s) indicated. I understand that I must comply with my employing company’s procedure for requesting leave (and provide additional documentation, such as medical certification if required) and that falsification on this form may grounds for disciplinary action, including removal.</div>
  <div class="signature-row"><div><span class="red" data-out="associate_name"></span><div style="border-top:1px solid #333;margin-top:15px;padding-top:3px">Associate’s signature</div></div><div><span class="red" data-out="date_prepared"></span><div style="border-top:1px solid #333;margin-top:15px;padding-top:3px">Date signed</div></div></div>
  <div class="approval-grid">
   <div><b>OFFICIAL ACTION ON REQUEST — MIS HEAD</b><br><span class="red" data-out="mis_decision"></span><div class="signspace"></div><span class="red" data-out="mis_head_name"></span> <span class="red" data-out="mis_decision_date"></span><br>Signature over printed name &amp; date<br><span class="red" data-out="mis_remarks"></span></div>
   <div><b>HR UNIT PROCESSING</b><br><span id="hrPrintStatus" class="red">Pending MIS Head approval</span><div class="signspace"></div><span class="red" data-out="approved_by"></span> <span class="red" data-out="approval_date"></span><br>Signature over printed name &amp; date</div>
  </div>
 </div>
</section>
<script>
const form=document.getElementById('rlfForm');
const dateNames=['date_prepared','effective_date','mis_decision_date','hr_signed','approval_date'];
function fmt(v){if(!v)return '';const d=new Date(v+'T00:00:00');return d.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});}
function get(n){const el=form.elements[n];return el?String(el.value||'').trim():'';}
function setOut(n,v){document.querySelectorAll('[data-out="'+n+'"]').forEach(el=>el.textContent=dateNames.includes(n)?fmt(v):v);}
function updatePreview(){
 ['date_prepared','company','department','associate_name','designation','employment_status','contact_number','designated_person','purpose','effective_date','days','vacation_credits','sick_credits','vacation_less','sick_less','vacation_balance','sick_balance','confirmed_by','hr_signed','mis_decision','mis_head_name','mis_decision_date','mis_remarks','approved_by','approval_date'].forEach(n=>setOut(n,get(n)));
 const nature=form.querySelector('input[name="nature"]:checked')?.value||'',other=get('nature_other');
 document.getElementById('printNature').innerHTML=['Vacation','Sick','Maternity','Others'].map(n=>'<div>'+(nature===n?'☑':'☐')+' '+n+(n==='Others'&&other?' — '+other:'')+'</div>').join('');
 const payment=form.querySelector('input[name="payment"]:checked')?.value||'';
 document.getElementById('printPayment').textContent=(payment==='With pay'?'☑':'☐')+' With pay　　 '+(payment==='Without pay'?'☑':'☐')+' Without pay';
 const decision=(document.getElementById('misDecision')?.value || '<?= $misApproved ? 'APPROVED' : 'PENDING' ?>').toUpperCase();
 const approved=decision==='APPROVED';
 const step2=document.getElementById('step2'),step3=document.getElementById('step3');
 if(step2) step2.className='step '+(approved?'done':'active');
 if(step3) step3.className='step '+(approved?'active':'');
 const hrStatus=document.getElementById('hrPrintStatus');
 if(hrStatus) hrStatus.textContent=approved?'Ready for HR processing':'Pending MIS Head approval';
}
form.addEventListener('input',updatePreview);form.addEventListener('change',updatePreview);
document.getElementById('previewBtn').addEventListener('click',updatePreview);
document.getElementById('printBtn').addEventListener('click',()=>{updatePreview();window.print();});
form.addEventListener('reset',()=>setTimeout(updatePreview,0));
document.querySelectorAll('input[name="nature"]').forEach(r=>r.addEventListener('change',()=>{document.getElementById('natureOther').disabled=r.value!=='Others'||!r.checked;if(r.value!=='Others')document.getElementById('natureOther').value='';updatePreview();}));
document.getElementById('misDecision').addEventListener('change',()=>{updatePreview();});
updatePreview();
</script>
</body></html>
