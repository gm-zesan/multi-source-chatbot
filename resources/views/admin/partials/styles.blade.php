{{-- Google Font --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">


{{-- bootstrap Css  --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">


{{-- Icon Library remixicon icon --}}
<link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">


{{-- Fontawesome Icon CSS --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />



{{-- Select2 css --}}
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container.select2-container--default {
        /* max-width: 600px;
        width: 100% !important; */
    }
    .select2-selection{
        height: 26px !important;
        border-radius: 4px !important;
        border: 1px solid #ced4da !important;
        margin-bottom: 10px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice{
        margin-top: 2px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__display{
        font-size: 12px;
        color: #1d1b31;
        font-weight: 500;
    }
    .select2-search--dropdown{
        padding: 0;
    }
    .select2-container--open .select2-dropdown--below{
        border: 1px solid #ced4da !important;
        border-top: none !important;
        border-radius: 4px !important;
        margin-top: -10px;
        z-index: 1062;
    }
    .select2-container--default .select2-selection--single:focus-visible{
        outline: none;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered{
        font-size: 12px;
        line-height: 25px;
        z-index: 1062;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered:focus-visible{
        outline: none;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field{
        padding: 4px 8px 4px 8px;
        border: none;
        border-bottom: 1px solid #ced4da;
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
        height: 26px;
        font-size: 12px;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field:focus-visible{
        outline: none;
    }
    .select2-results__options .select2-results__option.select2-results__option--disabled{
        font-size: 12px;
        height: 26px;
        padding: 4px 8px 4px 8px;
    }
    .select2-results__options::-webkit-scrollbar {
        width: 3px;
        background-color: #F5F5F5!important;
        background-clip: padding-box;
    }
    .select2-results__options::-webkit-scrollbar-track {
        background-color: #F5F5F5!important;
        background-clip: padding-box;
    }
    .select2-results__options::-webkit-scrollbar-thumb {
        background-clip: padding-box;
        background-color: rgb(216 220 241);
    }
    .select2-results__option--selectable {
        font-size: 12px;
        color: #1d1b31;
        font-weight: 500;
        height: 26px;
        padding: 4px 8px 4px 8px;
    }
    .select2-results__option--selectable:last-child {
        border-radius: 0 0 3px 3px;
    }
    .select2-results__options .select2-results__option.select2-results__message{
        height: 26px;
        font-size: 12px;
        padding: 4px 8px;
        color: #ff0000;
    }


    .multi-select2 + .select2-container .select2-search__field::placeholder {
        font-size: 13px;
    }
    .multi-select2 + .select2-container .select2-selection--multiple {
        min-height: 28px;
        height: 100%!important;
    }
    .multi-select2
        + .select2-container--default
        .select2-selection--multiple
        .select2-selection__choice {
        margin-top: 0px;
        max-height: 100%;
        min-height: 22px;
        line-height: 1;
    }
    .multi-select2
        + .select2-container
        .select2-search--inline
        .select2-search__field {
        height: 19px;
    }

</style>


{{-- DatePicker plugin --}}
<link href="https://unpkg.com/gijgo@1.9.14/css/gijgo.min.css" rel="stylesheet" type="text/css" />
<style>
    #datepicker{
        font-size: 13px;
        line-height: 18px;
    }
    .datepicker{
        font-size: 13px;
        line-height: 18px;
    }
    .gj-icon{
        font-size: 18px!important;
        top: 8px!important;
        right: 8px!important;
    }
    .modal {
        z-index: 1065 !important;
    }
    .modal-backdrop {
        z-index: 1055 !important;
    }

    /* Universal Modal Fade-In & Fade-Out Transitions */
    .modal.fade {
        opacity: 0;
        transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    .modal.fade .modal-dialog {
        transform: scale(0.96) translateY(-14px);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }
    .modal.fade.show {
        opacity: 1 !important;
    }
    .modal.fade.show .modal-dialog {
        transform: scale(1) translateY(0) !important;
    }
    .modal-backdrop.fade {
        opacity: 0;
        transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
        background-color: rgba(15, 23, 42, 0.5) !important;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
    .modal-backdrop.fade.show {
        opacity: 1 !important;
    }

    /* ==================== GLOBAL DATATABLES & BADGES FIX ==================== */
    .dataTables_wrapper {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .dataTables_wrapper table.dataTable {
        width: 100% !important;
        display: table !important;
        table-layout: auto !important;
    }
    table.dataTable {
        width: 100% !important;
        display: table !important;
    }

    /* Soft Badges - Universal High-Contrast */
    .badge-soft-primary, .badge.bg-primary.bg-opacity-10, .bg-primary.bg-opacity-10 {
        background-color: #eff6ff !important;
        color: #1d4ed8 !important;
        border: 1px solid #bfdbfe !important;
    }
    .badge-soft-success, .badge.bg-success.bg-opacity-10, .bg-success.bg-opacity-10 {
        background-color: #ecfdf5 !important;
        color: #047857 !important;
        border: 1px solid #a7f3d0 !important;
    }
    .badge-soft-warning, .badge.bg-warning.bg-opacity-10, .bg-warning.bg-opacity-10 {
        background-color: #fffbeb !important;
        color: #b45309 !important;
        border: 1px solid #fde68a !important;
    }
    .badge-soft-danger, .badge.bg-danger.bg-opacity-10, .bg-danger.bg-opacity-10 {
        background-color: #fef2f2 !important;
        color: #b91c1c !important;
        border: 1px solid #fecaca !important;
    }
    .badge-soft-info, .badge.bg-info.bg-opacity-10, .bg-info.bg-opacity-10 {
        background-color: #f0fdfa !important;
        color: #0f766e !important;
        border: 1px solid #99f6e4 !important;
    }
    .badge-soft-secondary, .badge.bg-secondary.bg-opacity-10, .bg-secondary.bg-opacity-10 {
        background-color: #f8fafc !important;
        color: #475569 !important;
        border: 1px solid #e2e8f0 !important;
    }

    /* Soft Icon Boxes */
    .icon-box-primary {
        background-color: #eff6ff !important;
        color: #2563eb !important;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .icon-box-success {
        background-color: #ecfdf5 !important;
        color: #059669 !important;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .icon-box-warning {
        background-color: #fffbeb !important;
        color: #d97706 !important;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .icon-box-info {
        background-color: #f0fdfa !important;
        color: #0891b2 !important;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .icon-box-danger {
        background-color: #fef2f2 !important;
        color: #dc2626 !important;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }
    .icon-box-secondary {
        background-color: #f1f5f9 !important;
        color: #475569 !important;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }

    /* ==================== UNIFIED PLATFORM CONTROLS & FORM SYNC ==================== */
    /* 1. All Table Action Buttons Identical Size (32x32px) */
    .action-btn,
    .d-flex.action-btn {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
    }
    .action-btn form {
        display: inline-flex !important;
        margin: 0 !important;
        padding: 0 !important;
        vertical-align: middle !important;
    }
    .action-btn .btn,
    .action-btn button,
    .action-btn a,
    .btn-assign-role,
    .btn-edit,
    .btn-delete,
    .btn-show,
    .btn-view,
    tbody tr td .btn-assign-role,
    tbody tr td .btn-edit,
    tbody tr td .btn-delete,
    tbody tr td .btn-show,
    tbody tr td .btn-view {
        width: 32px !important;
        height: 32px !important;
        min-width: 32px !important;
        min-height: 32px !important;
        max-width: 32px !important;
        max-height: 32px !important;
        padding: 0 !important;
        margin: 0 !important;
        border-radius: 6px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 15px !important;
        line-height: 1 !important;
        border: none !important;
        cursor: pointer !important;
        box-shadow: none !important;
        transition: all 0.15s ease !important;
        vertical-align: middle !important;
    }
    .btn-assign-role,
    .action-btn .btn-assign-role {
        background-color: #e0f2fe !important;
        color: #0284c7 !important;
    }
    .btn-assign-role:hover,
    .action-btn .btn-assign-role:hover {
        background-color: #0284c7 !important;
        color: #ffffff !important;
    }
    .btn-edit,
    .action-btn .btn-edit {
        background-color: #eff6ff !important;
        color: #2563eb !important;
    }
    .btn-edit:hover,
    .action-btn .btn-edit:hover {
        background-color: #2563eb !important;
        color: #ffffff !important;
    }
    .btn-delete,
    .action-btn .btn-delete {
        background-color: #fef2f2 !important;
        color: #dc2626 !important;
    }
    .btn-delete:hover,
    .action-btn .btn-delete:hover {
        background-color: #dc2626 !important;
        color: #ffffff !important;
    }
    .btn-show,
    .btn-view,
    .action-btn .btn-show,
    .action-btn .btn-view {
        background-color: #ecfdf5 !important;
        color: #059669 !important;
    }
    .btn-show:hover,
    .btn-view:hover,
    .action-btn .btn-show:hover,
    .action-btn .btn-view:hover {
        background-color: #059669 !important;
        color: #ffffff !important;
    }

    /* 2. Platform Form Inputs, Search Fields, Filter Fields Standard Height (38px) */
    input[type='text']:not(.select2-search__field),
    input[type='email'],
    input[type='password'],
    input[type='search'],
    input[type='number'],
    input[type='tel'],
    .form-control:not(textarea):not(.form-control-sm):not(.form-control-lg),
    .custom-input {
        height: 38px !important;
        min-height: 38px !important;
        border-radius: 8px !important;
        border: 1px solid #e2e8f0 !important;
        font-size: 0.875rem !important;
        padding: 0 12px !important;
        background-color: #ffffff !important;
        color: #1e293b !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease !important;
    }
    input[type='text']:focus,
    input[type='email']:focus,
    input[type='password']:focus,
    input[type='search']:focus,
    input[type='number']:focus,
    input[type='tel']:focus,
    .form-control:focus,
    .custom-input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        outline: none !important;
    }

    /* 3. Custom Select Field for Beautiful Modern View (Height 38px) */
    .form-select,
    select.custom-select,
    select.form-control,
    .dataTables_length select {
        height: 38px !important;
        min-height: 38px !important;
        border-radius: 8px !important;
        border: 1px solid #e2e8f0 !important;
        font-size: 0.875rem !important;
        padding: 0 2.25rem 0 12px !important;
        background-color: #ffffff !important;
        color: #1e293b !important;
        font-weight: 500 !important;
        cursor: pointer !important;
        appearance: none !important;
        -webkit-appearance: none !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 14px 10px !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease !important;
    }
    .form-select:hover,
    select.custom-select:hover,
    select.form-control:hover,
    .dataTables_length select:hover {
        border-color: #cbd5e1 !important;
        background-color: #f8fafc !important;
    }
    .form-select:focus,
    select.custom-select:focus,
    select.form-control:focus,
    .dataTables_length select:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        outline: none !important;
        background-color: #ffffff !important;
    }

    /* 4. Unified Search Bar Input Group (Height 38px) */
    .input-group {
        border-radius: 8px !important;
    }
    .input-group .input-group-text {
        height: 38px !important;
        min-height: 38px !important;
        border-radius: 8px 0 0 8px !important;
        border: 1px solid #e2e8f0 !important;
        border-right: none !important;
        background-color: #f8fafc !important;
        color: #64748b !important;
        font-size: 1rem !important;
        padding: 0 12px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    .input-group .form-control {
        height: 38px !important;
        min-height: 38px !important;
        border-radius: 0 8px 8px 0 !important;
        border: 1px solid #e2e8f0 !important;
        border-left: none !important;
        background-color: #ffffff !important;
    }
    .input-group:focus-within .input-group-text,
    .input-group:focus-within .form-control {
        border-color: #3b82f6 !important;
    }

    /* 5. DataTables Search Input & Length Filter */
    .dataTables_wrapper .dataTables_filter input {
        height: 38px !important;
        min-height: 38px !important;
        border-radius: 8px !important;
        border: 1px solid #e2e8f0 !important;
        padding: 0 12px !important;
        background-color: #ffffff !important;
        font-size: 0.875rem !important;
        margin-left: 8px !important;
        outline: none !important;
        transition: all 0.15s ease !important;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
    }
    .dataTables_wrapper .dataTables_length select {
        margin: 0 6px !important;
        width: auto !important;
        display: inline-block !important;
    }

    /* 6. Standard Platform Buttons & Filter Buttons (Height 38px) */
    .btn:not(.btn-sm):not(.action-btn .btn):not(.btn-edit):not(.btn-delete):not(.btn-assign-role):not(.btn-show):not(.btn-view),
    .add-new,
    .btn-range-filter {
        height: 38px !important;
        min-height: 38px !important;
        border-radius: 8px !important;
        font-size: 0.875rem !important;
        font-weight: 500 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        line-height: 1 !important;
        padding: 0 16px !important;
        box-sizing: border-box !important;
        transition: all 0.15s ease !important;
    }
    .btn-group .btn {
        height: 38px !important;
        min-height: 38px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 14px !important;
        border: 1px solid #e2e8f0 !important;
    }
    .btn-group .btn:first-child {
        border-radius: 8px 0 0 8px !important;
    }
    .btn-group .btn:last-child {
        border-radius: 0 8px 8px 0 !important;
    }
</style>


{{-- <!-- Style css --> --}}
@vite(['resources/scss/admin/style.scss', 'resources/js/app.js'])
