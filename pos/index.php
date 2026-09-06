<?php

require_once "../config/database.php";
require_once "../auth.php";

requireLogin();


// ==========================================
// LOAD AVAILABLE PRODUCTS
// ==========================================

$products = $conn->query("
    SELECT
        id,
        product_code,
        product_name,
        selling_price,
        stock_quantity
    FROM products
    WHERE stock_quantity > 0
    ORDER BY product_name ASC
");

if (!$products) {
    die("Unable to load products.");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        POS - Small Business System
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/global.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/pos.css"
    >

    <style>

        /* ==========================================
           PRODUCT CODE
        ========================================== */

        .product-code {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 8px;
        }


        /* ==========================================
           DISABLED CHECKOUT
        ========================================== */

        .checkout:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }


        /* ==========================================
           PRODUCT CLICK FEEDBACK
        ========================================== */

        .product {
            cursor: pointer;
            user-select: none;
        }


        .product:active {
            transform: scale(0.98);
        }

    </style>

</head>


<body>


<!-- ==========================================
     POS HEADER
========================================== -->

<div class="header">

    <h1>
        POS
    </h1>


    <div class="header-right">

        <span>
            <?= htmlspecialchars(
                $_SESSION["full_name"] ?? "Cashier",
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </span>


        <a
            href="../logout.php"
            class="logout-btn"
        >
            Logout
        </a>

    </div>

</div>


<!-- ==========================================
     POS CONTAINER
========================================== -->

<div class="container">


    <!-- ==========================================
         PRODUCTS PANEL
    =========================================== -->

    <div class="products-panel">


        <input
            type="text"
            id="search"
            class="search"
            placeholder="Search product..."
            autocomplete="off"
        >


        <div
            class="products"
            id="products"
        >


            <?php if ($products->num_rows > 0): ?>


                <?php while (
                    $product = $products->fetch_assoc()
                ): ?>


                    <div
                        class="product"

                        data-id="<?= (int) $product["id"] ?>"

                        data-product-name="<?= htmlspecialchars(
                            $product["product_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"

                        data-price="<?= htmlspecialchars(
                            $product["selling_price"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"

                        data-stock="<?= (int) $product["stock_quantity"] ?>"

                        data-search="<?= htmlspecialchars(
                            strtolower(
                                $product["product_name"] .
                                " " .
                                $product["product_code"]
                            ),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >


                        <div class="product-name">

                            <?= htmlspecialchars(
                                $product["product_name"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>


                        <div class="product-code">

                            <?= htmlspecialchars(
                                $product["product_code"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>

                        </div>


                        <div class="price">

                            ₱<?= number_format(
                                (float) $product["selling_price"],
                                2
                            ) ?>

                        </div>


                        <div class="stock">

                            Stock:
                            <?= number_format(
                                (int) $product["stock_quantity"]
                            ) ?>

                        </div>


                    </div>


                <?php endwhile; ?>


            <?php else: ?>


                <div class="empty">

                    No products available.

                </div>


            <?php endif; ?>


        </div>


    </div>


    <!-- ==========================================
         CART PANEL
    =========================================== -->

    <div class="cart-panel">


        <h2>
            Current Sale
        </h2>


        <div
            id="cart"
            class="cart-items"
        >

            <div class="empty">

                No products added.

            </div>

        </div>


        <!-- ======================================
             SUMMARY
        ======================================= -->

        <div class="summary">


            <!-- SUBTOTAL -->

            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <strong id="subtotal">
                    ₱0.00
                </strong>

            </div>


            <!-- DISCOUNT -->

            <label>

                Discount

                <input
                    type="number"
                    id="discount"
                    class="discount"
                    value="0"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                >

            </label>


            <!-- TOTAL -->

            <div class="summary-row total">

                <span>
                    Total
                </span>

                <span id="total">
                    ₱0.00
                </span>

            </div>


            <!-- PAYMENT -->

            <label>

                Payment

                <input
                    type="number"
                    id="payment"
                    class="payment"
                    value="0"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                >

            </label>


            <!-- CHANGE -->

            <div class="summary-row">

                <span>
                    Change
                </span>

                <span
                    id="change"
                    class="change"
                >
                    ₱0.00
                </span>

            </div>


            <!-- CHECKOUT -->

            <button
                type="button"
                id="checkoutButton"
                class="checkout"
            >
                COMPLETE SALE
            </button>


        </div>


    </div>


</div>


<script>


// ==========================================
// CART
// ==========================================

let cart = [];


// ==========================================
// DOM ELEMENTS
// ==========================================

const productElements =
    document.querySelectorAll(
        ".product"
    );

const searchInput =
    document.getElementById(
        "search"
    );

const discountInput =
    document.getElementById(
        "discount"
    );

const paymentInput =
    document.getElementById(
        "payment"
    );

const checkoutButton =
    document.getElementById(
        "checkoutButton"
    );


// ==========================================
// PRODUCT CLICK EVENTS
// ==========================================

productElements.forEach(
    function(product) {

        product.addEventListener(
            "click",
            function() {


                const id =
                    Number(
                        product.dataset.id
                    );


                const name =
                    product.dataset.productName;


                const price =
                    Number(
                        product.dataset.price
                    );


                const stock =
                    Number(
                        product.dataset.stock
                    );


                addToCart(
                    id,
                    name,
                    price,
                    stock
                );

            }
        );

    }
);


// ==========================================
// SEARCH EVENT
// ==========================================

if (searchInput) {

    searchInput.addEventListener(
        "input",
        searchProducts
    );

}


// ==========================================
// DISCOUNT EVENT
// ==========================================

if (discountInput) {

    discountInput.addEventListener(
        "input",
        calculateTotal
    );

}


// ==========================================
// PAYMENT EVENT
// ==========================================

if (paymentInput) {

    paymentInput.addEventListener(
        "input",
        calculateChange
    );

}


// ==========================================
// CHECKOUT EVENT
// ==========================================

if (checkoutButton) {

    checkoutButton.addEventListener(
        "click",
        completeSale
    );

}


// ==========================================
// ADD TO CART
// ==========================================

function addToCart(
    id,
    name,
    price,
    stock
) {


    id =
        Number(id);


    price =
        Number(price);


    stock =
        Number(stock);


    // --------------------------------------
    // VALIDATE PRODUCT
    // --------------------------------------

    if (
        !Number.isInteger(id) ||
        id <= 0
    ) {

        alert(
            "Invalid product."
        );

        return;
    }


    if (
        !Number.isFinite(price) ||
        price < 0
    ) {

        alert(
            "Invalid product price."
        );

        return;
    }


    if (
        !Number.isInteger(stock) ||
        stock <= 0
    ) {

        alert(
            "Product is out of stock."
        );

        return;
    }


    // --------------------------------------
    // CHECK EXISTING CART ITEM
    // --------------------------------------

    const existing =
        cart.find(
            function(item) {

                return item.id === id;

            }
        );


    if (existing) {


        if (
            existing.quantity >=
            existing.stock
        ) {

            alert(
                "Not enough stock!"
            );

            return;
        }


        existing.quantity++;


    } else {


        cart.push({

            id: id,

            name: String(name),

            price: price,

            quantity: 1,

            stock: stock

        });

    }


    renderCart();

}


// ==========================================
// RENDER CART
// ==========================================

function renderCart() {


    const cartContainer =
        document.getElementById(
            "cart"
        );


    if (
        cart.length === 0
    ) {

        cartContainer.innerHTML = `

            <div class="empty">
                No products added.
            </div>

        `;


        calculateTotal();

        return;
    }


    let html = "";


    cart.forEach(
        function(item, index) {


            const itemSubtotal =
                item.price *
                item.quantity;


            html += `

                <div class="cart-item">


                    <div class="cart-name">

                        ${escapeHtml(
                            item.name
                        )}

                    </div>


                    <div class="cart-controls">


                        <button
                            type="button"
                            class="qty-btn"
                            data-action="decrease"
                            data-index="${index}"
                        >
                            −
                        </button>


                        <span class="quantity">

                            ${item.quantity}

                        </span>


                        <button
                            type="button"
                            class="qty-btn"
                            data-action="increase"
                            data-index="${index}"
                        >
                            +
                        </button>


                        <button
                            type="button"
                            class="qty-btn remove"
                            data-action="remove"
                            data-index="${index}"
                        >
                            ×
                        </button>


                        <span class="cart-price">

                            ₱${itemSubtotal.toFixed(2)}

                        </span>


                    </div>


                </div>

            `;

        }
    );


    cartContainer.innerHTML =
        html;


    // --------------------------------------
    // CART BUTTON EVENTS
    // --------------------------------------

    const cartButtons =
        cartContainer.querySelectorAll(
            "button[data-action]"
        );


    cartButtons.forEach(
        function(button) {


            button.addEventListener(
                "click",
                function(event) {


                    event.stopPropagation();


                    const index =
                        Number(
                            button.dataset.index
                        );


                    const action =
                        button.dataset.action;


                    if (
                        action ===
                        "increase"
                    ) {

                        changeQuantity(
                            index,
                            1
                        );

                    }


                    else if (
                        action ===
                        "decrease"
                    ) {

                        changeQuantity(
                            index,
                            -1
                        );

                    }


                    else if (
                        action ===
                        "remove"
                    ) {

                        removeItem(
                            index
                        );

                    }

                }
            );

        }
    );


    calculateTotal();

}


// ==========================================
// ESCAPE HTML
// ==========================================

function escapeHtml(text) {


    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        String(text);


    return div.innerHTML;

}


// ==========================================
// CHANGE QUANTITY
// ==========================================

function changeQuantity(
    index,
    amount
) {


    if (
        !cart[index]
    ) {

        return;
    }


    const item =
        cart[index];


    const newQuantity =
        item.quantity +
        amount;


    if (
        newQuantity <= 0
    ) {

        cart.splice(
            index,
            1
        );


    } else if (
        newQuantity >
        item.stock
    ) {

        alert(
            "Not enough stock!"
        );

        return;


    } else {

        item.quantity =
            newQuantity;

    }


    renderCart();

}


// ==========================================
// REMOVE ITEM
// ==========================================

function removeItem(index) {


    if (
        !cart[index]
    ) {

        return;
    }


    cart.splice(
        index,
        1
    );


    renderCart();

}


// ==========================================
// GET SUBTOTAL
// ==========================================

function getCartSubtotal() {


    let subtotal = 0;


    cart.forEach(
        function(item) {

            subtotal +=
                Number(item.price) *
                Number(item.quantity);

        }
    );


    return subtotal;

}


// ==========================================
// GET DISCOUNT
// ==========================================

function getDiscount() {


    const subtotal =
        getCartSubtotal();


    let discount =
        parseFloat(
            discountInput.value
        );


    if (
        !Number.isFinite(discount) ||
        discount < 0
    ) {

        discount = 0;
    }


    if (
        discount > subtotal
    ) {

        discount = subtotal;
    }


    return discount;

}


// ==========================================
// CALCULATE TOTAL
// ==========================================

function calculateTotal() {


    const subtotal =
        getCartSubtotal();


    const discount =
        getDiscount();


    const total =
        subtotal -
        discount;


    document.getElementById(
        "subtotal"
    ).innerText =
        "₱" +
        subtotal.toFixed(2);


    document.getElementById(
        "total"
    ).innerText =
        "₱" +
        total.toFixed(2);


    calculateChange();

}


// ==========================================
// CALCULATE CHANGE
// ==========================================

function calculateChange() {


    const subtotal =
        getCartSubtotal();


    const discount =
        getDiscount();


    const total =
        subtotal -
        discount;


    let payment =
        parseFloat(
            paymentInput.value
        );


    if (
        !Number.isFinite(payment) ||
        payment < 0
    ) {

        payment = 0;
    }


    const change =
        Math.max(
            0,
            payment -
            total
        );


    document.getElementById(
        "change"
    ).innerText =
        "₱" +
        change.toFixed(2);

}


// ==========================================
// SEARCH PRODUCTS
// ==========================================

function searchProducts() {


    const search =
        searchInput.value
            .toLowerCase()
            .trim();


    productElements.forEach(
        function(product) {


            const productSearch =
                product.dataset.search ||
                "";


            if (
                productSearch.includes(
                    search
                )
            ) {

                product.style.display =
                    "";


            } else {

                product.style.display =
                    "none";

            }

        }
    );

}


// ==========================================
// COMPLETE SALE
// ==========================================

async function completeSale() {


    // --------------------------------------
    // CART CHECK
    // --------------------------------------

    if (
        cart.length === 0
    ) {

        alert(
            "Please add a product first."
        );

        return;
    }


    // --------------------------------------
    // VALUES
    // --------------------------------------

    const subtotal =
        getCartSubtotal();


    let discount =
        parseFloat(
            discountInput.value
        );


    let payment =
        parseFloat(
            paymentInput.value
        );


    if (
        !Number.isFinite(discount)
    ) {

        discount = 0;
    }


    if (
        !Number.isFinite(payment)
    ) {

        payment = 0;
    }


    // --------------------------------------
    // VALIDATE DISCOUNT
    // --------------------------------------

    if (
        discount < 0
    ) {

        alert(
            "Discount cannot be negative."
        );

        return;
    }


    if (
        discount > subtotal
    ) {

        alert(
            "Discount cannot exceed subtotal."
        );

        return;
    }


    // --------------------------------------
    // CASHIER DISCOUNT LIMIT
    // --------------------------------------

    const maximumDiscount =
        subtotal * 0.10;


    if (
        discount >
        maximumDiscount +
        0.000001
    ) {

        alert(

            "Discount is too high.\n\n" +

            "Cashier discount limit: 10%\n" +

            "Maximum allowed discount: ₱" +

            maximumDiscount.toFixed(2)

        );

        return;
    }


    // --------------------------------------
    // TOTAL
    // --------------------------------------

    const total =
        subtotal -
        discount;


    // --------------------------------------
    // PAYMENT
    // --------------------------------------

    if (
        payment < total
    ) {

        alert(

            "Payment is not enough.\n\n" +

            "Total: ₱" +
            total.toFixed(2)

        );

        return;
    }


    // --------------------------------------
    // CHANGE
    // --------------------------------------

    const change =
        payment -
        total;


    // --------------------------------------
    // CONFIRM
    // --------------------------------------

    const confirmed =
        confirm(

            "Complete this sale?\n\n" +

            "Subtotal: ₱" +
            subtotal.toFixed(2) +

            "\nDiscount: ₱" +
            discount.toFixed(2) +

            "\nTotal: ₱" +
            total.toFixed(2) +

            "\nPayment: ₱" +
            payment.toFixed(2) +

            "\nChange: ₱" +
            change.toFixed(2)

        );


    if (
        !confirmed
    ) {

        return;
    }


    // --------------------------------------
    // DISABLE BUTTON
    // --------------------------------------

    checkoutButton.disabled =
        true;


    checkoutButton.innerText =
        "PROCESSING...";


    // --------------------------------------
    // SEND TO SERVER
    // --------------------------------------

    try {


        const response =
            await fetch(
                "process_sale.php",
                {

                    method: "POST",

                    headers: {

                        "Content-Type":
                            "application/json",

                        "Accept":
                            "application/json"

                    },

                    body:
                        JSON.stringify({

                            cart: cart,

                            discount:
                                discount,

                            payment:
                                payment

                        })

                }
            );


        // ----------------------------------
        // HTTP ERROR
        // ----------------------------------

        if (
            !response.ok
        ) {

            throw new Error(
                "HTTP " +
                response.status
            );

        }


        // ----------------------------------
        // READ RESPONSE
        // ----------------------------------

        const result =
            await response.json();


        // ----------------------------------
        // SUCCESS
        // ----------------------------------

        if (
            result.success
        ) {

            window.location.href =
                "receipt.php?id=" +
                encodeURIComponent(
                    result.sale_id
                );

            return;
        }


        // ----------------------------------
        // SERVER ERROR
        // ----------------------------------

        alert(

            result.message ||
            "Unable to complete sale."

        );


    } catch (error) {


        console.error(
            "POS Error:",
            error
        );


        alert(

            "An error occurred while processing the sale.\n\n" +
            "Please try again."

        );


    } finally {


        checkoutButton.disabled =
            false;


        checkoutButton.innerText =
            "COMPLETE SALE";

    }

}


// ==========================================
// INITIALIZE
// ==========================================

renderCart();

calculateTotal();

</script>


</body>

</html>