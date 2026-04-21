# 🚀 Class Manager Pro – WordPress Class Management Plugin

<p align="center">
  <b>Advanced Class, Batch & Student Management System for WordPress</b><br>
  ⚡ Automation • 🎓 Student Tracking • 💳 Payment Integration • 📊 Admin Control
</p>

---

<p align="center">

![Version](https://img.shields.io/badge/version-1.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-Plugin-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Status](https://img.shields.io/badge/status-Active-success)
![PHP](https://img.shields.io/badge/PHP-Compatible-purple)

</p>

---

## 👨‍💻 Developed By

**Swaraj Fugare**

🌐 Portfolio: https://portfolio.matoshreecollection.in
🏢 Website: https://matoshreecollection.in

---

## 🌟 About The Plugin

**Class Manager Pro** is a complete solution for managing:

* Classes
* Batches
* Students
* Attendance
* Payments
* Integrations (Tutor LMS & Razorpay)

All from a **single powerful WordPress dashboard**.

---

## ✨ Key Features

* 📚 Class & Batch Management
* 👨‍🎓 Student Records & Tracking
* 📊 Attendance System
* 💳 Razorpay Payment Integration
* 🎓 Tutor LMS Enrollment Support
* 🔄 AJAX-based Admin Actions
* 🛡️ Secure Data Handling

---

## 📸 Screenshots (Add Your Images Here)

```id="img1"
/assets/dashboard.png
/assets/batch.png
/assets/student.png
```

---

## ⚙️ Installation Guide

1. Upload plugin folder to:

```
/wp-content/plugins/class-manager-pro/
```

2. Activate plugin from WordPress Admin

3. Open:

```
Class Manager Pro → Dashboard
```

---

## 🚀 Usage Overview

* Create Classes
* Add Batches
* Add Students
* Track Attendance
* Manage Payments
* Export Data

---

## 🔧 Integrations

### 💳 Razorpay

* Payment import support
* Webhook integration
* Secure transaction handling

### 🎓 Tutor LMS

* Auto enrollment
* Course linking with batches

---

## 📂 File Structure

* `class-manager-pro.php` : Main plugin bootstrap
* `includes/` : Core logic, DB, integrations
* `admin/` : Admin UI pages
* `assets/js/` : JavaScript
* `assets/css/` : Styling

---

## 🧠 Developer Notes

* Uses WordPress hooks & AJAX
* Role & capability checks included
* Modular structure for easy expansion

---

## 🔐 Security

✔ Admin capability checks
✔ Secure AJAX handling
✔ Input validation
✔ Safe database queries

---

# 📌 Existing Documentation (Do Not Modify)

> 🔽 Below content is original project documentation (kept 100% intact)

---

## Recommended Live Deployment Checklist

Before going live:

1. Test plugin activation on staging first

2. Confirm WordPress admin access is working

3. Confirm plugin menu loads correctly

4. Create one class, one batch, and one student

5. Test delete for student, batch, and class

6. Test `Delete All Plugin Data` only on staging

7. Test attendance save inside batch page

8. Test Tutor LMS enrollment if Tutor LMS is active

9. Test Razorpay webhook or import if Razorpay is in use

10. Export CSV backups before production changes

---

## Troubleshooting

### Plugin does not appear in WordPress

* Confirm the folder name is `class-manager-pro`

* Confirm the main file is:

`class-manager-pro/class-manager-pro.php`

* Confirm the plugin was uploaded inside `wp-content/plugins/`

---

### Delete button does not remove records

* Confirm you are logged in as an admin user

* Confirm WordPress AJAX is working

* Check browser console and network tab

* Confirm no security plugin is blocking `admin-ajax.php`

---

### Razorpay import does not work

* Confirm Razorpay key ID and secret are saved

* Confirm webhook secret is correct

* Confirm payment page is linked to the right batch

---

### Tutor LMS enrollment does not work

* Confirm Tutor LMS is active

* Confirm the batch has a valid linked Tutor LMS course

* Confirm the student has a valid phone/email/user mapping

---

## File Structure

* `class-manager-pro.php` : Main plugin bootstrap

* `includes/` : Core logic, DB, Tutor LMS, Razorpay, helpers

* `admin/` : Admin pages

* `assets/js/` : Admin JavaScript

* `assets/css/` : Admin styles

---

## Final Notes

* Keep a backup before production deployment

* Use staging for first validation

* Upload only the plugin folder or plugin zip

* Do not rename internal files unless you also update references

---

## 📜 License

MIT License

---

## ❤️ Support

If you like this project:

⭐ Star this repository
🔗 Share with others
🌐 Visit: https://matoshreecollection.in

---

<p align="center">
⚡ Built with passion by <b>Swaraj Fugare</b>
</p>
