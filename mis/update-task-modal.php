<?php
// Update Task modal partial. Included by user-dashboard.php.
?>
<div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalTitle" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-centered">
  <div class="modal-content">
   <div class="modal-header">
    <div>
     <h5 class="modal-title" id="updateModalTitle"><i class="bi bi-pencil-square me-2"></i>Update Task</h5>
     <small id="modalReport" class="text-white-50"></small>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
   </div>

   <form id="updateForm">
    <div class="modal-body">
     <input type="hidden" name="action" value="update_task">
     <input type="hidden" name="task_id" id="taskId">

     <div class="row mb-3">
      <div class="col-md-7">
       <small class="text-secondary">TASK TITLE</small>
       <div class="fw-bold" id="modalTitle"></div>
      </div>
      <div class="col-md-5">
       <small class="text-secondary">COMPANY</small>
       <div class="fw-semibold" id="modalCompany"></div>
      </div>
     </div>

     <div class="mb-3">
      <label class="form-label" for="modalStatus">Update Status <span class="text-danger">*</span></label>
      <select class="form-select" name="status" id="modalStatus" required>
       <option value="">Select status</option>
      </select>
     </div>

     <div class="row g-3">
      <div class="col-md-6 d-none" id="diagnosisGroup">
       <label class="form-label" for="modalDiagnosis">Diagnosis <span class="text-danger d-none" id="diagnosisRequired">*</span></label>
       <textarea class="form-control" name="diagnosis" id="modalDiagnosis" rows="3" placeholder="Describe the cause or findings..."></textarea>
      </div>

      <div class="col-md-6 d-none" id="actionGroup">
       <label class="form-label" for="modalAction">Action Taken <span class="text-danger d-none" id="actionRequired">*</span></label>
       <textarea class="form-control" name="action_taken" id="modalAction" rows="3" placeholder="Describe the work performed..."></textarea>
      </div>

      <div class="col-md-6 d-none" id="recommendationGroup">
       <label class="form-label" for="modalRecommendation"><span id="recommendationLabel">Reason for Incomplete</span> <span class="text-danger">*</span></label>
       <textarea class="form-control" name="recommendation" id="modalRecommendation" rows="3" placeholder="Explain why this task cannot be completed yet and what is needed next..."></textarea>
       <div class="form-text" id="recommendationHelp">This is saved in the Recommendation field.</div>
      </div>
     </div>

     <div class="alert alert-light border small mt-3 mb-0" id="updateHelp">
      <i class="bi bi-info-circle text-primary me-1"></i>
      Choose a status to see the information required for that update.
     </div>
    </div>

    <div class="modal-footer">
     <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
     <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Update</button>
    </div>
   </form>
  </div>
 </div>
</div>
