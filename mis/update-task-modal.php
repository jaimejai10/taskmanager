<?php
// MIS Helpdesk - polished Update Task modal partial.
?>
<div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="updateModalLabel"><i class="bi bi-pencil-square me-2"></i>Update Task</h5>
                    <small id="modalReport" class="text-white-50"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="updateForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_task">
                    <input type="hidden" name="task_id" id="taskId">

                    <div class="row g-3 mb-4">
                        <div class="col-md-7"><div class="task-meta-card"><span class="task-meta-label">TASK TITLE</span><div class="fw-bold" id="modalTitle">—</div></div></div>
                        <div class="col-md-5"><div class="task-meta-card"><span class="task-meta-label">COMPANY</span><div class="fw-semibold" id="modalCompany">—</div></div></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="modalStatus">Update Status <span class="text-danger">*</span></label>
                        <select class="form-select" name="status" id="modalStatus" required>
                            <option value="">Select status</option>
                            <option>In Progress</option><option>Complete</option><option>Incomplete</option><option>On Hold</option>
                        </select>
                    </div>

                    <div class="update-section-title">Work Details</div>
                    <div class="row g-3">
                        <div class="col-md-6" id="diagnosisGroup">
                            <label class="form-label" for="modalDiagnosis">Diagnosis <span class="text-danger d-none" id="diagnosisRequired">*</span></label>
                            <textarea class="form-control" name="diagnosis" id="modalDiagnosis" rows="4" placeholder="Describe the issue or findings..."></textarea>
                        </div>
                        <div class="col-md-6" id="actionGroup">
                            <label class="form-label" for="modalAction">Action Taken <span class="text-danger d-none" id="actionRequired">*</span></label>
                            <textarea class="form-control" name="action_taken" id="modalAction" rows="4" placeholder="Describe the troubleshooting or work performed..."></textarea>
                        </div>
                        <div class="col-md-6 d-none" id="recommendationGroup">
                            <label class="form-label" for="modalRecommendation"><span id="recommendationLabel">Reason for Incomplete</span> <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="recommendation" id="modalRecommendation" rows="4" placeholder="Explain what is preventing completion..."></textarea>
                            <small class="text-secondary" id="recommendationHelp">Please provide the reason.</small>
                        </div>
                        <div class="col-md-6 d-none" id="remarksGroup">
                            <label class="form-label" for="modalRemarks">Remarks</label>
                            <textarea class="form-control" name="remarks" id="modalRemarks" rows="4" placeholder="Additional remarks..."></textarea>
                        </div>
                    </div>
                    <div class="alert alert-light border small mt-4 mb-0" id="updateHelp"><i class="bi bi-info-circle text-primary me-1"></i>Required fields change automatically depending on the selected status.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save me-1"></i>Save Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
