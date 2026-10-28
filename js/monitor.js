
return `
    <tr>
        <td>
            <span class="report-number">
                ${escapeHTML(request.report_id || request.task_id || "—")}
            </span>
        </td>
        <td>
            <strong>${escapeHTML(request.title || "Untitled Request")}</strong>
            <div class="secondary-text">
                ${escapeHTML(request.category || "General")}
            </div>
        </td>
        <td>
            ${escapeHTML(request.company_name || "—")}
            <div class="secondary-text">
                ${escapeHTML(request.requested_by || "—")}
            </div>
        </td>
        <td>
            <span class="status-badge ${statusClass(status)}">
                ${escapeHTML(status)}
            </span>
        </td>
        <td>${escapeHTML(formatDate(request.created_at))}</td>
    </tr>`;
