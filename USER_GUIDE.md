# POS System — User Guide

A practical, non-technical guide to running the Point of Sale system day to day.
If you can use a web browser, you can use this guide.

---

## 1. What this system is

This is a multi-branch **restaurant / retail Point of Sale**. From one browser it lets you:

- Take orders (dine-in, take-away, delivery) and accept payments
- Manage products, deals, variants (sizes), and add-on modifiers
- Track customers, discounts, coupons, and tips
- Run each **counter** (till/station) separately and reconcile its **cash drawer**
- Track ingredients with a **Bill of Materials** so stock is consumed automatically
- See detailed **sales and cash reports**

The system is **multi-tenant**: one installation can host many independent businesses ("tenants"), each with its own branches, staff, products, and data.

---

## 2. Who does what (roles)

| Role | Can do |
|------|--------|
| **Super Admin** | Runs the whole platform: manages all tenants and subscription plans. |
| **Admin** (store owner/manager) | Everything inside their own business: POS, products, staff, counters, inventory, reports, settings. |
| **Cashier** | Runs the POS: takes orders, selects a counter, opens/closes the cash drawer, applies coupons/discounts. |
| **Editor** | Manages the catalog (products, categories, coupons, suppliers) and can view reports. |

You only see the menu items your role is allowed to use.

---

## 3. Logging in

1. Open the app in your browser (ask your admin for the address).
2. Enter your **email** and **password** and sign in.
3. If **Two-Factor Authentication** is enabled on your account, enter the 6-digit code from your authenticator app.
4. You land on the **Dashboard**.

To sign out, use **Logout** at the bottom of the left sidebar.

---

## 4. Taking a sale (the POS screen)

Open **POS** from the sidebar. The screen has two halves: **products on the left**, the **cart/checkout on the right**.

### Step by step
1. **Choose the service type** at the top of the cart: **Dine In**, **Take Away**, or **Delivery**.
2. **Choose the Counter** (your till/station). Every sale is recorded against this counter.
3. Fill the context fields that appear:
   - Dine In → **Table** + **Waiter**
   - Take Away → **Waiter**
   - Delivery → **Rider** (and optionally a **Delivery Zone**, which can add a fee)
4. **Add products**: click a product card.
   - If it has **sizes/variants** (e.g. Small/Medium/Large), pick one.
   - If it has **modifiers** (e.g. extra cheese), tick the options.
   - **Deals** are added as a fixed-price bundle.
5. **Adjust the cart**: use **− / +** to change quantity, set a **DISC %** on any line for a per-item discount, or the trash icon to remove it.
6. **Pick a customer** (optional): click **Select customer**, search by name or phone. If the number is new, press **Enter** to open the quick "add customer" form. Leave blank for a walk-in.
7. **Charges & Tax** (optional): open this to set **GST/Tax %**, a **service charge %**, or an **order-level discount** (percent or fixed).
8. **Coupon** (optional): type a coupon code and **Apply**.
9. **Tip** (optional): enter a tip amount.
10. **Payment**:
    - Choose **Cash**, **Card**, or **Other**. For cash, enter **Cash Received** — the system shows the **change**.
    - Or tick **Split payment** to record several tenders (e.g. part cash, part card).
11. Press **Checkout**. The order is saved and you can **Print Receipt** or start a **New Sale**.

### Handy POS features
- **Hold**: park an unfinished cart (e.g. table still ordering) and resume it later from **⏸ Held**.
- **Search / category tabs**: quickly filter the product grid.
- **Stock**: out-of-stock items are greyed out (deals and recipe-based items follow their own rules).

---

## 5. Customers

Open **Customers** to manage your customer list. You can add name, contact, email, address, city, and a default **discount** (percent or fixed) that can be applied at checkout. Customers can also be created on the fly from the POS customer picker.

---

## 6. Counters (tills / stations)

Open **Operations → Counters** (admin). Create one counter per physical till, e.g. *Counter 1*, *Counter 2*. Each counter can belong to a branch. On the POS, the cashier picks their counter, so every sale — and every cash-drawer shift — is tracked per counter. This powers the **Counter-wise** sales report.

---

## 7. Cash drawer (opening & closing cash)

Open **Cash Drawer**.

1. **Open a shift** at the start: enter the **opening cash** (float) in the drawer. (Tie it to your counter.)
2. Take sales as normal during the shift.
3. **Close the shift** at the end: count the physical cash and enter the **actual cash**. The system computes:
   - **Expected** = opening cash + cash sales during the shift
   - **Variance** = actual − expected (over/short)
4. You get a **Z-Report** summarising the shift (sales by payment method, voids, refunds, totals).

Only one shift can be open per counter/branch at a time.

---

## 8. Inventory & Bill of Materials (BOM)

For businesses that track ingredients:

1. **Raw Materials** (*Inventory / BOM → Raw Materials*): add ingredients with a unit (g, ml, pcs…), cost per unit, current stock, and an optional **low-stock threshold** (rows turn amber when low).
2. **Bill of Materials** (*Inventory / BOM → Bill of Materials*): pick a product, then list the raw materials and **quantity per unit** that make it. The screen shows the **recipe cost**.
3. From then on, **selling that product automatically deducts its raw materials** from stock. Voiding or refunding the sale puts them back.

---

## 9. Reports

Open **Reports** in the sidebar — it expands into a list of focused reports. All of them respect the **date range** and **Export CSV** at the top.

| Report | Shows |
|--------|-------|
| **Summary** | Headline numbers: orders, gross sales, cash vs credit, tax, discounts, gross profit, top products. |
| **Sale Type-wise** | Totals split by Dine-in / Take-away / Delivery. |
| **Day-wise** | Sales per day across the range. |
| **Item-wise** | Every product sold: quantity, discount, revenue. |
| **Category-wise** | Sales grouped by product category. |
| **Payment-wise** | Totals by payment method (cash, card, wallet, bank…). |
| **Cash vs Credit** | Cash sales vs credit (any non-cash tender). |
| **Counter-wise** | Sales per counter/till. |
| **Cashier-wise** | Sales per staff member who rang them up. |
| **Open/Close Cash** | Every shift with opening, expected, counted cash and the variance. |

> **Cash vs Credit**: a sale is **Cash** when paid with cash, and **Credit** when paid by any non-cash method (card, wallet, bank, etc.).

---

## 10. Other admin modules

Depending on your role you may also see:

- **Products / Categories / Modifiers / Deals** — your catalog.
- **Coupons** — discount codes with limits.
- **Delivery Zones** — areas with delivery fees and ETAs.
- **Suppliers / Purchase Orders / Stock Adjustments** — purchasing and stock control.
- **Waiters / Tables / Riders / Branches** — operations setup.
- **Time Entries / Payroll** — staff hours and pay.
- **FBR Submissions** — tax-authority invoice submissions (Pakistan).
- **Users / Roles & Permissions** — who can access what.
- **Audit Log** — a record of sensitive actions (voids, deletes, etc.).
- **Two-Factor Auth** — secure your own login.

---

## 11. Tips for smooth daily use

- Open your **cash drawer** shift before the first sale; close it at the end and check the variance.
- Pick the correct **counter** and **service type** before adding items.
- Use **Hold** for tables still deciding, and resume when they're ready.
- Check the **Summary** and **Cash vs Credit** reports at the end of the day, and the **Open/Close Cash** report to confirm the till balanced.
- If product images or prices look wrong, tell your admin — it's usually a settings/stock issue, not a POS bug.
