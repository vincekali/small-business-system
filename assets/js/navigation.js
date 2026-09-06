/* ==========================================
   MOBILE NAVIGATION
========================================== */

const sidebar =
    document.getElementById("sidebar");

const mobileMenuBtn =
    document.getElementById("mobileMenuBtn");

const sidebarCloseBtn =
    document.getElementById("sidebarCloseBtn");

const mobileOverlay =
    document.getElementById("mobileOverlay");


/* ==========================================
   OPEN SIDEBAR
========================================== */

function openSidebar() {

    if (!sidebar) {
        return;
    }

    sidebar.classList.add("open");


    if (mobileOverlay) {

        mobileOverlay.classList.add("show");

    }


    if (mobileMenuBtn) {

        mobileMenuBtn.setAttribute(
            "aria-expanded",
            "true"
        );

        /*
           Hide burger while sidebar is open.
           The X button inside the sidebar
           becomes the close control.
        */
        mobileMenuBtn.style.display = "none";

    }


    /*
       Prevent background page from scrolling
       while the drawer is open.
    */
    document.body.style.overflow = "hidden";
}


/* ==========================================
   CLOSE SIDEBAR
========================================== */

function closeSidebar() {

    if (!sidebar) {
        return;
    }

    sidebar.classList.remove("open");


    if (mobileOverlay) {

        mobileOverlay.classList.remove("show");

    }


    if (mobileMenuBtn) {

        mobileMenuBtn.setAttribute(
            "aria-expanded",
            "false"
        );

        /*
           Restore burger button.
        */
        mobileMenuBtn.style.display = "";

    }


    /*
       Restore page scrolling.
    */
    document.body.style.overflow = "";
}


/* ==========================================
   BURGER BUTTON
========================================== */

if (mobileMenuBtn) {

    mobileMenuBtn.addEventListener(
        "click",
        openSidebar
    );

}


/* ==========================================
   CLOSE BUTTON
========================================== */

if (sidebarCloseBtn) {

    sidebarCloseBtn.addEventListener(
        "click",
        closeSidebar
    );

}


/* ==========================================
   OVERLAY
========================================== */

if (mobileOverlay) {

    mobileOverlay.addEventListener(
        "click",
        closeSidebar
    );

}


/* ==========================================
   CLOSE AFTER CLICKING LINK
========================================== */

if (sidebar) {

    const sidebarLinks =
        sidebar.querySelectorAll("a");


    sidebarLinks.forEach(
        function(link) {

            link.addEventListener(
                "click",
                closeSidebar
            );

        }
    );

}


/* ==========================================
   ESCAPE KEY
========================================== */

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            closeSidebar();

        }

    }
);


/* ==========================================
   WINDOW RESIZE
========================================== */

window.addEventListener(
    "resize",
    function() {

        /*
           If user rotates phone or
           increases browser width,
           automatically close drawer.
        */

        if (window.innerWidth > 700) {

            closeSidebar();

        }

    }
);