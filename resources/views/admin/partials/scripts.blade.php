{{-- Jquery CDN --}}
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

{{-- bootstrap script  --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
</script>


{{-- sweet alert --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js" integrity="sha512-AA1Bzp5Q0K1KanKKmvN/4d3IRKVlv9PYgwFPvm32nPO6QS8yH1HO7LbgB1pgiOxPtfeg5zEn2ba64MUcqJx6CA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

@if(session('success'))
    <script>
        swal({
            title: "Success!",
            text: "{{ session('success') }}",
            icon: "success",
            button: "OK",
        });
    </script>
@endif

@if(session('error'))
    <script>
        swal({
            title: "Error!",
            text: "{{ session('error') }}",
            icon: "error",
            button: "OK",
        });
    </script>
@endif


{{-- Select 2 --}}
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.single-select2').select2();
    });
    $(document).ready(function() {
        $('.multiple-select2').select2({
            placeholder: "Select teachers from here",
            allowClear: true
        });
    });
</script>



{{-- DatePicker plugin --}}
<script src="https://unpkg.com/gijgo@1.9.14/js/gijgo.min.js" type="text/javascript"></script>
<script>
    $('#datepicker').datepicker({
        format: 'yyyy-mm-dd',
        showOtherMonths: true
    });
    
</script>


{{-- Sidebar toggle functionality --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.querySelector('#btn');
        const sidebar = document.querySelector('.sidebar');

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

            // Close sidebar on mobile when clicking a link
            const navLinks = sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        sidebar.classList.remove('active');
                    }
                });
            });

            // Responsive sidebar behavior
            function checkScreenSize() {
                if (window.innerWidth < 992) {
                    sidebar.classList.remove('active');
                } else {
                    sidebar.classList.add('active');
                }
            }

            checkScreenSize();
            window.addEventListener('resize', checkScreenSize);
        }
    });
</script>

{{-- Logout form functionality --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const logoutForm = document.getElementById('logout-form');
        const sidebarLogoutBtn = document.querySelector('.sidebar .logout-btn');
        
        if (sidebarLogoutBtn) {
            sidebarLogoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (logoutForm) {
                    logoutForm.submit();
                }
            });
        }
    });
</script>

<!-- Common Scripts -->
<script>
    var SITEURL = "{{ URL::to('') }}";
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        var SITEURL = "{{ URL::to('') }}";
        var ASSET_URL = "{{ config('app.asset_url') }}/";
    });
</script>


<script>
    function setProfileImage(e, image) {
        if (image) {
            $('#profileImage').attr('src', e.target.result).show();
            $('#sidebarImage').attr('src', e.target.result).show();
            $('#sidebarImage').removeClass('d-none');
            $('#profileImage').removeClass('d-none');

            $('.profile-icon').addClass('d-none');
            $('#profileImageDB').addClass('d-none');
            $('#sidebarImageDB').addClass('d-none');
        } else {
            $('#profileImage').addClass('d-none');
            $('#sidebarImage').addClass('d-none');

            $('.profile-icon').removeClass('d-none');
            $('#profileImageDB').removeClass('d-none');
            $('#sidebarImageDB').removeClass('d-none');
        }
    }
</script>



{{-- full screen js  --}}
<script>
    // full screen open
    var elem = document.getElementById("fullpage");

    function openFullscreen() {
        $("#openFullScreen").addClass("d-none");
        $("#exitFullScreen").removeClass("d-none");
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }
    }
    // full screen exit
    function removeFullScreen() {
        $("#openFullScreen").removeClass("d-none");
        $("#exitFullScreen").addClass("d-none");
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
</script>

{{-- Global Bootstrap Modal Stacking Context & Backdrop Trap Fix --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function moveModalsToBody() {
            document.querySelectorAll('.modal').forEach(function(modalEl) {
                if (modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }
            });
        }
        moveModalsToBody();

        document.addEventListener('show.bs.modal', function (e) {
            if (e.target && e.target.parentElement !== document.body) {
                document.body.appendChild(e.target);
            }
        });
    });
</script>