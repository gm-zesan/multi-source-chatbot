<div class="sidebar-wrapper">

    <div class="sidebar-header">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h4 class="fw-bold mb-0">
                    Inbox
                </h4>

                <small class="text-muted">

                    Customer conversations

                </small>

            </div>

            {{-- <button class="btn btn-light rounded-circle">

                <i class="bi bi-sliders"></i>

            </button> --}}

        </div>

    </div>

    <div class="search-box-wrapper">
        
        <div class="search-box">

            <i class="bi bi-search"></i>

            <input
                type="text"
                placeholder="Search conversations">

        </div>

    </div>

    <div class="conversation-list mt-3">

        @include('conversations.partials.conversation-list')

    </div>

</div>
