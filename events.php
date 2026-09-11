<?php
// events.php - Fully dynamic events page with same design as exams

require_once '../database/connect.php';
require_once 'event_process.php';

// Get student ID from session (for demo, using student_id = 4)
$student_id = 4;

// Initialize the event process
$eventProcess = new EventProcess($conn, $student_id);

// Get all data
$student = $eventProcess->getStudentData();
$upcomingEvents = $eventProcess->getUpcomingEvents();
$pastEvents = $eventProcess->getPastEvents();
$allEvents = $eventProcess->getAllEvents();
$stats = $eventProcess->getEventStats();
$nextEvent = $eventProcess->getNextEvent();

// Format student name for display
$studentName = htmlspecialchars($student['first_name'] ?? 'Ahmed') . ' ' . htmlspecialchars($student['last_name'] ?? 'Faraz');
$studentInitials = strtoupper(substr($student['first_name'] ?? 'A', 0, 1) . substr($student['last_name'] ?? 'F', 0, 1));
$studentClass = htmlspecialchars($student['class_name'] ?? 'Class 10');
$studentUID = htmlspecialchars($student['student_uid'] ?? 'STU-1024');

// Get total events count
$totalEvents = $stats['total'] ?? 0;
$upcomingCount = $stats['upcoming'] ?? 0;
$pastCount = $stats['past'] ?? 0;
$confirmedCount = $stats['confirmed'] ?? 0;
$planningCount = $stats['planning'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="Events — Crescent Public School Student Portal" />
  <title>Events &middot; Student Portal &middot; Crescent Public School</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml" />
  <style>
    /* ===== All styles from exams page ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #0a0e1a;
      color: #e8edf5;
      min-height: 100vh;
      display: flex;
    }

    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    ::-webkit-scrollbar-track {
      background: #141b2b;
    }

    ::-webkit-scrollbar-thumb {
      background: #2a3a5a;
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #3a4a6a;
    }

    .app-sidebar {
      width: 260px;
      min-height: 100vh;
      background: linear-gradient(180deg, #0d1225 0%, #0a0e1a 100%);
      border-right: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      z-index: 100;
    }

    .sidebar-head {
      padding: 20px 20px 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #e8edf5;
    }

    .sidebar-brand img {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: rgba(96, 165, 250, 0.15);
      padding: 6px;
    }

    .brand-text strong {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      letter-spacing: -0.3px;
    }

    .brand-text small {
      font-size: 0.6rem;
      opacity: 0.4;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .sidebar-close {
      display: none;
      color: #e8edf5;
      font-size: 1.2rem;
      cursor: pointer;
    }

    .student-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 20px;
      margin: 12px 16px 8px;
      background: linear-gradient(135deg, rgba(96, 165, 250, 0.08), rgba(96, 165, 250, 0.02));
      border-radius: 14px;
      border: 1px solid rgba(96, 165, 250, 0.06);
    }

    .avatar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6, #6366f1);
      color: #fff;
      font-weight: 700;
      font-size: 0.85rem;
      flex-shrink: 0;
    }

    .avatar-lg {
      width: 44px;
      height: 44px;
      font-size: 1rem;
    }

    .avatar.violet {
      background: linear-gradient(135deg, #8b5cf6, #a78bfa);
    }

    .student-card-text {
      flex: 1;
      min-width: 0;
    }

    .student-card-text strong {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .student-card-text small {
      font-size: 0.65rem;
      opacity: 0.5;
      display: block;
    }

    .verify {
      color: #34d399;
      font-size: 0.85rem;
    }

    .sidebar-nav {
      flex: 1;
      padding: 8px 12px 20px;
      overflow-y: auto;
    }

    .nav-group {
      padding: 16px 12px 6px;
      font-size: 0.6rem;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      opacity: 0.3;
      font-weight: 600;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 9px 12px;
      border-radius: 10px;
      color: #7a8aa8;
      text-decoration: none;
      transition: all 0.2s;
      font-size: 0.85rem;
      font-weight: 500;
      position: relative;
    }

    .nav-item:hover {
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
    }

    .nav-item.active {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(99, 102, 241, 0.06));
      color: #60a5fa;
    }

    .nav-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 24px;
      background: linear-gradient(180deg, #3b82f6, #6366f1);
      border-radius: 0 4px 4px 0;
    }

    .nav-item i {
      font-size: 1.1rem;
      width: 20px;
      text-align: center;
      flex-shrink: 0;
    }

    .nav-item span {
      flex: 1;
    }

    .nav-tag {
      background: rgba(255, 255, 255, 0.06);
      padding: 1px 10px;
      border-radius: 12px;
      font-size: 0.6rem;
      font-weight: 600;
    }

    .nav-tag-info {
      background: rgba(96, 165, 250, 0.15);
      color: #60a5fa;
    }

    .nav-tag-warn {
      background: rgba(251, 191, 36, 0.15);
      color: #fbbf24;
    }

    .nav-tag-danger {
      background: rgba(239, 68, 68, 0.15);
      color: #f87171;
    }

    .sidebar-foot {
      padding: 12px 20px 16px;
      border-top: 1px solid rgba(255, 255, 255, 0.04);
      margin-top: auto;
    }

    .logout-btn {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 9px 12px;
      border-radius: 10px;
      color: #f87171;
      text-decoration: none;
      transition: all 0.2s;
      font-size: 0.85rem;
      font-weight: 500;
    }

    .logout-btn:hover {
      background: rgba(239, 68, 68, 0.08);
    }

    .copy {
      font-size: 0.6rem;
      opacity: 0.2;
      margin: 8px 0 0 12px;
      letter-spacing: 0.3px;
    }

    .app-main {
      flex: 1;
      min-height: 100vh;
      background: linear-gradient(180deg, #0d1225 0%, #0a0e1a 100%);
      display: flex;
      flex-direction: column;
    }

    .app-topbar {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 14px 28px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      background: rgba(13, 18, 37, 0.8);
      backdrop-filter: blur(12px);
      position: sticky;
      top: 0;
      z-index: 50;
      flex-wrap: wrap;
    }

    .nav-btn {
      display: none;
      font-size: 1.4rem;
      color: #e8edf5;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px;
    }

    .topbar-title {
      flex: 1;
      min-width: 120px;
    }

    .topbar-title h1 {
      font-size: 1.3rem;
      font-weight: 700;
      margin: 0;
      letter-spacing: -0.5px;
      background: linear-gradient(135deg, #e8edf5, #94a3b8);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .topbar-title .crumbs {
      font-size: 0.7rem;
      opacity: 0.35;
      margin-top: 2px;
      -webkit-text-fill-color: #94a3b8;
    }

    .topbar-title .crumbs a {
      color: #94a3b8;
      text-decoration: none;
      -webkit-text-fill-color: #94a3b8;
    }

    .topbar-title .crumbs a:hover {
      color: #e8edf5;
    }

    .topbar-title .crumbs span {
      margin: 0 4px;
    }

    .topbar-search {
      display: flex;
      align-items: center;
      background: rgba(255, 255, 255, 0.04);
      border-radius: 10px;
      padding: 6px 14px;
      border: 1px solid rgba(255, 255, 255, 0.04);
      transition: all 0.3s;
      min-width: 200px;
    }

    .topbar-search:focus-within {
      border-color: rgba(96, 165, 250, 0.3);
      background: rgba(255, 255, 255, 0.06);
    }

    .topbar-search i {
      opacity: 0.3;
      margin-right: 10px;
      font-size: 0.9rem;
    }

    .topbar-search input {
      background: transparent;
      border: none;
      color: #e8edf5;
      padding: 6px 0;
      outline: none;
      width: 100%;
      font-size: 0.85rem;
    }

    .topbar-search input::placeholder {
      color: #4a5a7a;
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .icon-btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: 10px;
      color: #7a8aa8;
      text-decoration: none;
      transition: all 0.2s;
      border: none;
      background: transparent;
      cursor: pointer;
    }

    .icon-btn:hover {
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
    }

    .icon-btn .ping {
      position: absolute;
      top: 2px;
      right: 2px;
      background: #ef4444;
      color: #fff;
      font-size: 0.55rem;
      padding: 1px 6px;
      border-radius: 10px;
      min-width: 18px;
      text-align: center;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    }

    .profile-chip {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 4px 14px 4px 4px;
      border-radius: 30px;
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
      border: 1px solid rgba(255, 255, 255, 0.04);
    }

    .profile-chip:hover {
      background: rgba(255, 255, 255, 0.08);
    }

    .profile-chip .who {
      line-height: 1.2;
    }

    .profile-chip .who b {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
    }

    .profile-chip .who small {
      font-size: 0.6rem;
      opacity: 0.4;
    }

    .profile-chip .bi-chevron-down {
      font-size: 0.7rem;
      opacity: 0.3;
    }

    .icon-drop {
      position: relative;
    }

    .drop-panel {
      display: none;
      position: absolute;
      right: 0;
      top: calc(100% + 8px);
      min-width: 280px;
      background: #141b2b;
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 14px;
      padding: 6px 0;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
      z-index: 200;
      backdrop-filter: blur(20px);
    }

    .icon-drop:hover .drop-panel {
      display: block;
    }

    .drop-head {
      display: flex;
      justify-content: space-between;
      padding: 10px 16px 10px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      font-size: 0.85rem;
    }

    .drop-head a {
      color: #60a5fa;
      text-decoration: none;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .drop-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 16px;
      color: #e8edf5;
      text-decoration: none;
      transition: background 0.2s;
      font-size: 0.85rem;
    }

    .drop-row:hover {
      background: rgba(255, 255, 255, 0.03);
    }

    .drop-row p {
      margin: 0;
    }

    .drop-row small {
      font-size: 0.65rem;
      opacity: 0.4;
      display: block;
    }

    .dot-ico {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.04);
      flex-shrink: 0;
    }

    .dot-ico.p-info {
      background: rgba(96, 165, 250, 0.12);
      color: #60a5fa;
    }

    .dot-ico.p-warn {
      background: rgba(251, 191, 36, 0.12);
      color: #fbbf24;
    }

    .dot-ico.p-danger {
      background: rgba(239, 68, 68, 0.12);
      color: #f87171;
    }

    .dot-ico.p-teal {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .dot-ico.p-violet {
      background: rgba(167, 139, 250, 0.12);
      color: #a78bfa;
    }

    .dot-ico.p-ok {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .app-content {
      padding: 24px 28px 20px;
      flex: 1;
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
    }

    .welcome {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.06), rgba(99, 102, 241, 0.03));
      border-radius: 20px;
      padding: 28px 32px;
      border: 1px solid rgba(255, 255, 255, 0.04);
      position: relative;
      overflow: hidden;
    }

    .welcome::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(59, 130, 246, 0.04), transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }

    .welcome .eyebrow {
      display: inline-block;
      font-size: 0.65rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      opacity: 0.35;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .welcome h2 {
      font-size: 1.6rem;
      font-weight: 700;
      margin: 0 0 6px;
      letter-spacing: -0.5px;
    }

    .welcome p {
      opacity: 0.6;
      font-size: 0.9rem;
      margin: 0;
      max-width: 600px;
    }

    .welcome p b {
      color: #60a5fa;
      opacity: 1;
    }

    .quick-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 14px;
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 14px;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.04);
      color: #94a3b8;
      text-decoration: none;
      font-size: 0.75rem;
      font-weight: 500;
      transition: all 0.2s;
      border: 1px solid rgba(255, 255, 255, 0.04);
    }

    .chip:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #e8edf5;
      border-color: rgba(255, 255, 255, 0.08);
    }

    .chip i {
      font-size: 0.8rem;
    }

    .fact {
      background: rgba(255, 255, 255, 0.03);
      padding: 12px 14px;
      border-radius: 12px;
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.04);
      height: 100%;
    }

    .fact small {
      display: block;
      font-size: 0.6rem;
      opacity: 0.3;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
    }

    .fact strong {
      display: block;
      font-size: 1.1rem;
      font-weight: 700;
      margin: 4px 0 2px;
      color: #e8edf5;
    }

    .fact span {
      font-size: 0.7rem;
      opacity: 0.4;
    }

    .card {
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.005));
      border: 1px solid rgba(255, 255, 255, 0.04);
      border-radius: 16px;
      overflow: hidden;
      transition: all 0.3s;
    }

    .card.lift:hover {
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
    }

    .card .card-head {
      padding: 18px 22px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
    }

    .card .card-head h3 {
      margin: 0;
      font-size: 1rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .card .card-head h3 .sub {
      font-size: 0.7rem;
      opacity: 0.4;
      font-weight: 400;
    }

    .card .card-body {
      padding: 20px 22px;
    }

    .card .card-body.tight {
      padding: 12px 16px;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 12px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.2px;
    }

    .pill.p-grey {
      background: rgba(255, 255, 255, 0.04);
      color: #5a6a8a;
    }

    .pill.p-ok {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .pill.p-warn {
      background: rgba(251, 191, 36, 0.12);
      color: #fbbf24;
    }

    .pill.p-danger {
      background: rgba(239, 68, 68, 0.12);
      color: #f87171;
    }

    .pill.p-info {
      background: rgba(96, 165, 250, 0.12);
      color: #60a5fa;
    }

    .pill.p-teal {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .pill.p-violet {
      background: rgba(167, 139, 250, 0.12);
      color: #a78bfa;
    }

    .meta-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .meta-list li {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      font-size: 0.85rem;
    }

    .meta-list li:last-child {
      border-bottom: none;
    }

    .meta-list li i {
      color: #60a5fa;
      margin-right: 10px;
      width: 18px;
      text-align: center;
      opacity: 0.6;
    }

    .meta-list li span {
      color: #5a6a8a;
      font-weight: 500;
    }

    .subject-ico {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 48px;
      height: 48px;
      border-radius: 12px;
      font-size: 1.3rem;
      flex-shrink: 0;
    }

    .subject-ico.teal {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .subject-ico.info {
      background: rgba(96, 165, 250, 0.12);
      color: #60a5fa;
    }

    .subject-ico.amber {
      background: rgba(251, 191, 36, 0.12);
      color: #fbbf24;
    }

    .subject-ico.violet {
      background: rgba(167, 139, 250, 0.12);
      color: #a78bfa;
    }

    .subject-ico.ok {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .subject-ico.danger {
      background: rgba(239, 68, 68, 0.12);
      color: #f87171;
    }

    .notice-date {
      min-width: 48px;
      text-align: center;
      background: rgba(255, 255, 255, 0.04);
      border-radius: 10px;
      padding: 6px 10px;
      flex-shrink: 0;
    }

    .notice-date b {
      display: block;
      font-size: 1.1rem;
      font-weight: 700;
      color: #60a5fa;
    }

    .notice-date small {
      font-size: 0.55rem;
      opacity: 0.5;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      display: block;
    }

    .notice-row {
      display: flex;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      align-items: flex-start;
    }

    .notice-row:last-child {
      border-bottom: none;
    }

    .notice-row .notice-date {
      min-width: 40px;
      text-align: center;
      padding-top: 2px;
      flex-shrink: 0;
    }

    .notice-row .notice-date b {
      display: block;
      font-size: 1rem;
      font-weight: 700;
      color: #60a5fa;
    }

    .notice-row .notice-date small {
      font-size: 0.55rem;
      opacity: 0.3;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .notice-row h4 {
      font-size: 0.85rem;
      font-weight: 600;
      margin: 0 0 2px;
    }

    .notice-row p {
      font-size: 0.75rem;
      opacity: 0.4;
      margin: 0 0 4px;
    }

    .notice-row.teal .notice-date b {
      color: #34d399;
    }

    .notice-row.amber .notice-date b {
      color: #fbbf24;
    }

    .notice-row.info .notice-date b {
      color: #60a5fa;
    }

    .notice-row.violet .notice-date b {
      color: #a78bfa;
    }

    .table-responsive {
      overflow-x: auto;
    }

    .table {
      color: #e8edf5;
      margin: 0;
      font-size: 0.85rem;
      width: 100%;
      border-collapse: collapse;
    }

    .table thead th {
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      color: #5a6a8a;
      font-weight: 600;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 12px 16px;
      text-align: left;
    }

    .table tbody td {
      padding: 12px 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.03);
      vertical-align: middle;
    }

    .table tbody tr:last-child td {
      border-bottom: none;
    }

    .table tbody tr:hover {
      background: rgba(255, 255, 255, 0.02);
    }

    .t-strong {
      font-weight: 600;
    }

    .divider-soft {
      height: 1px;
      background: rgba(255, 255, 255, 0.04);
      margin: 12px 0;
    }

    .btn-outline {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 20px;
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.06);
      color: #94a3b8;
      text-decoration: none;
      transition: all 0.2s;
      background: transparent;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
    }

    .btn-outline:hover {
      background: rgba(255, 255, 255, 0.04);
      border-color: rgba(255, 255, 255, 0.12);
      color: #e8edf5;
    }

    .btn-solid {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 20px;
      border-radius: 10px;
      background: linear-gradient(135deg, #3b82f6, #6366f1);
      color: #fff;
      text-decoration: none;
      transition: all 0.2s;
      border: none;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
    }

    .btn-solid:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    }

    .w-100 {
      width: 100%;
    }

    .justify-content-center {
      justify-content: center;
    }

    .gap-2 {
      gap: 8px;
    }

    .mt-2 {
      margin-top: 8px;
    }

    .mt-3 {
      margin-top: 16px;
    }

    .mb-0 {
      margin-bottom: 0;
    }

    .mb-2 {
      margin-bottom: 8px;
    }

    .mb-3 {
      margin-bottom: 16px;
    }

    .page-foot {
      padding: 16px 28px;
      border-top: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
      font-size: 0.75rem;
      opacity: 0.25;
      margin-top: auto;
    }

    .page-foot a {
      color: #94a3b8;
      text-decoration: none;
      transition: color 0.2s;
    }

    .page-foot a:hover {
      color: #e8edf5;
    }

    .page-foot .d-flex {
      display: flex;
      gap: 16px;
    }

    .nav-toggle {
      display: none;
    }

    .nav-backdrop {
      display: none;
    }

    @media (max-width: 992px) {
      .app-sidebar {
        position: fixed;
        left: -280px;
        top: 0;
        bottom: 0;
        width: 280px;
        z-index: 1000;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        border-right: none;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
      }

      .nav-toggle:checked~.app-sidebar {
        left: 0;
      }

      .nav-toggle:checked~.nav-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 999;
        backdrop-filter: blur(4px);
      }

      .sidebar-close {
        display: inline-flex;
      }

      .nav-btn {
        display: inline-flex;
      }

      .app-topbar {
        padding: 12px 16px;
      }

      .app-content {
        padding: 16px;
      }

      .welcome {
        padding: 20px;
      }

      .welcome h2 {
        font-size: 1.3rem;
      }

      .topbar-search {
        min-width: 120px;
      }

      .profile-chip .who {
        display: none;
      }

      .card .card-head {
        padding: 14px 16px;
      }

      .card .card-body {
        padding: 14px 16px;
      }

      .page-foot {
        flex-direction: column;
        text-align: center;
        padding: 12px 16px;
      }

      .topbar-actions .icon-drop:not(:last-child) {
        display: none;
      }

      .topbar-title h1 {
        font-size: 1.1rem;
      }

      .col-md-6 {
        margin-bottom: 12px;
      }
    }

    @media (max-width: 576px) {
      .welcome .row .col-lg-8 {
        margin-bottom: 16px;
      }

      .fact {
        padding: 8px 10px;
      }

      .fact strong {
        font-size: 0.95rem;
      }

      .quick-chips .chip {
        font-size: 0.7rem;
        padding: 4px 10px;
      }

      .card .card-head h3 {
        font-size: 0.9rem;
      }

      .card .card-head h3 .sub {
        font-size: 0.6rem;
      }

      .pill {
        font-size: 0.6rem;
        padding: 1px 8px;
      }

      .notice-date {
        min-width: 36px;
      }

      .notice-date b {
        font-size: 0.9rem;
      }

      .meta-list li {
        font-size: 0.75rem;
      }

      .subject-ico {
        width: 36px;
        height: 36px;
        font-size: 1rem;
      }
    }

    @media print {

      .app-sidebar,
      .app-topbar,
      .page-foot {
        display: none !important;
      }

      .app-main {
        background: white !important;
        color: black !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      .app-content {
        padding: 20px !important;
        max-width: 100% !important;
      }

      .card {
        border: 1px solid #ddd !important;
        background: white !important;
        box-shadow: none !important;
        margin-bottom: 16px !important;
        page-break-inside: avoid !important;
      }

      .card .card-head {
        border-bottom: 2px solid #ddd !important;
        padding: 12px 16px !important;
      }

      .card .card-head h3 {
        color: #333 !important;
      }

      .card .card-head h3 .sub {
        color: #666 !important;
        opacity: 1 !important;
      }

      .card .card-body {
        padding: 16px !important;
      }

      .welcome {
        border: 1px solid #ddd !important;
        background: #f9f9f9 !important;
        padding: 20px !important;
        border-radius: 8px !important;
      }

      .welcome h2 {
        color: #333 !important;
      }

      .welcome p {
        color: #555 !important;
        opacity: 1 !important;
      }

      .welcome .eyebrow {
        color: #666 !important;
        opacity: 1 !important;
      }

      .fact {
        border: 1px solid #ddd !important;
        background: #f9f9f9 !important;
      }

      .fact strong {
        color: #333 !important;
      }

      .fact small {
        color: #666 !important;
        opacity: 1 !important;
      }

      .fact span {
        color: #666 !important;
        opacity: 1 !important;
      }

      .pill {
        background: #eee !important;
        color: #333 !important;
        border: 1px solid #ddd !important;
      }

      .meta-list li {
        border-bottom: 1px solid #eee !important;
        color: #333 !important;
      }

      .meta-list li span {
        color: #666 !important;
      }

      .meta-list li i {
        color: #666 !important;
      }

      .table {
        color: #333 !important;
      }

      .table thead th {
        color: #333 !important;
        border-bottom: 2px solid #ddd !important;
        background: #f5f5f5 !important;
      }

      .table tbody td {
        color: #333 !important;
        border-bottom: 1px solid #eee !important;
      }

      .notice-row {
        border-bottom: 1px solid #eee !important;
      }

      .notice-row h4 {
        color: #333 !important;
      }

      .notice-row p {
        color: #666 !important;
        opacity: 1 !important;
      }

      .notice-date b {
        color: #2563eb !important;
      }

      .notice-date small {
        color: #666 !important;
        opacity: 1 !important;
      }

      .subject-ico {
        background: #f5f5f5 !important;
        color: #333 !important;
        border: 1px solid #ddd !important;
      }
    }
  </style>
</head>

<body>

  <input type="checkbox" id="navToggle" class="nav-toggle" />
  <label for="navToggle" class="nav-backdrop" aria-hidden="true"></label>

  <!-- ===== SIDEBAR ===== -->
  <aside class="app-sidebar">
    <div class="sidebar-head">
      <a href="index.php" class="sidebar-brand">
        <img src="assets/images/logo.svg" alt="Crescent Public School logo" />
        <span class="brand-text"><strong>Crescent Public School</strong><small>Student Portal</small></span>
      </a>
      <label for="navToggle" class="sidebar-close" aria-label="Close navigation"><i class="bi bi-x-lg"></i></label>
    </div>

    <div class="student-card">
      <span class="avatar avatar-lg">
        <?php echo $studentInitials; ?>
      </span>
      <div class="student-card-text">
        <strong>
          <?php echo $studentName; ?>
        </strong>
        <small>
          <?php echo $studentClass . ' · ' . $studentUID; ?>
        </small>
      </div>
      <span class="verify" title="Verified student"><i class="bi bi-patch-check-fill"></i></span>
    </div>

    <nav class="sidebar-nav">
      <p class="nav-group">Overview</p>
      <a class="nav-item" href="index.php"><i class="bi bi-columns-gap"></i><span>Dashboard</span></a>

      <p class="nav-group">Academics</p>
      <a class="nav-item" href="subjects.php"><i class="bi bi-journal-bookmark"></i><span>My Subjects</span></a>
      <a class="nav-item" href="timetable.php"><i class="bi bi-calendar-week"></i><span>My Timetable</span></a>
      <a class="nav-item" href="attendance.php"><i class="bi bi-check2-square"></i><span>My Attendance</span></a>
      <a class="nav-item" href="assignments.php"><i class="bi bi-journal-text"></i><span>Assignments</span></a>
      <a class="nav-item" href="exams.php"><i class="bi bi-pencil-square"></i><span>Exams</span></a>
      <a class="nav-item" href="results.php"><i class="bi bi-graph-up-arrow"></i><span>Results</span></a>

      <p class="nav-group">Finance</p>
      <a class="nav-item" href="fees.php"><i class="bi bi-wallet2"></i><span>Fees</span></a>

      <p class="nav-group">School Life</p>
      <a class="nav-item" href="notices.php"><i class="bi bi-megaphone"></i><span>Notices</span></a>
      <a class="nav-item active" href="events.php"><i class="bi bi-calendar2-heart"></i><span>Events</span><em
          class="nav-tag nav-tag-info">
          <?php echo $upcomingCount; ?>
        </em></a>
      <a class="nav-item" href="messages.php"><i class="bi bi-envelope"></i><span>Messages</span></a>

      <p class="nav-group">Account</p>
      <a class="nav-item" href="profile.php"><i class="bi bi-person-badge"></i><span>My Profile</span></a>
      <a class="nav-item" href="settings.php"><i class="bi bi-gear"></i><span>Settings</span></a>
    </nav>

    <div class="sidebar-foot">
      <a href="#" class="logout-btn"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
      <p class="copy">Portal v2.6 &middot; Session 2026&ndash;27</p>
    </div>
  </aside>

  <!-- ===== MAIN CONTENT ===== -->
  <div class="app-main">

    <!-- ===== TOPBAR ===== -->
    <header class="app-topbar">
      <label for="navToggle" class="nav-btn" aria-label="Open navigation"><i class="bi bi-list"></i></label>
      <div class="topbar-title">
        <h1>Events</h1>
        <div class="crumbs"><a href="index.php">Home</a><span>/</span>School Life<span>/</span>Events</div>
      </div>
      <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="search" placeholder="Search events…" aria-label="Search" />
      </div>
      <div class="topbar-actions">
        <div class="icon-drop">
          <a href="notices.php" class="icon-btn" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <span class="ping">
              <?php echo min($upcomingCount, 9); ?>
            </span>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Notifications</strong><a href="notices.php">View all</a></div>
            <?php if (!empty($upcomingEvents)): ?>
              <?php foreach (array_slice($upcomingEvents, 0, 3) as $event): ?>
                <a href="events.php" class="drop-row">
                  <i class="dot-ico <?php echo $event['status_color']; ?>"><i class="bi bi-calendar2-heart"></i></i>
                  <span>
                    <p>
                      <?php echo htmlspecialchars(substr($event['event_name'], 0, 40)) . (strlen($event['event_name']) > 40 ? '...' : ''); ?>
                    </p>
                    <small>
                      <?php echo $event['days_left'] ?? date('M d, Y', strtotime($event['event_date'])); ?>
                    </small>
                  </span>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="drop-row"><span>
                  <p style="opacity:0.4;">No upcoming events</p>
                </span></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="icon-drop">
          <a href="messages.php" class="icon-btn" aria-label="Messages"><i class="bi bi-envelope"></i></a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Messages</strong><a href="messages.php">Open inbox</a></div>
            <div class="drop-row"><span>
                <p style="opacity:0.4;">No new messages</p>
              </span></div>
          </div>
        </div>
        <div class="icon-drop">
          <a href="profile.php" class="profile-chip">
            <span class="avatar">
              <?php echo $studentInitials; ?>
            </span>
            <span class="who"><b>
                <?php echo htmlspecialchars($student['first_name'] ?? 'Ahmed'); ?>
              </b><small>
                <?php echo $studentClass; ?>
              </small></span>
            <i class="bi bi-chevron-down"></i>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>
                <?php echo $studentName; ?>
              </strong><span class="pill p-ok">Active</span></div>
            <a href="profile.php" class="drop-row"><i class="dot-ico p-teal"><i
                  class="bi bi-person-badge"></i></i><span>
                <p>My Profile</p><small>
                  <?php echo $studentUID; ?>
                </small>
              </span></a>
            <a href="settings.php" class="drop-row"><i class="dot-ico p-violet"><i class="bi bi-gear"></i></i><span>
                <p>Settings</p><small>Preferences &amp; alerts</small>
              </span></a>
            <a href="#" class="drop-row"><i class="dot-ico p-danger"><i class="bi bi-box-arrow-right"></i></i><span>
                <p>Logout</p><small>End this session</small>
              </span></a>
          </div>
        </div>
      </div>
    </header>

    <!-- ===== CONTENT ===== -->
    <main class="app-content">

      <!-- ===== DEBUG INFO (Remove after testing) ===== -->
      <div
        style="background:rgba(255,255,255,0.05);padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:0.8rem;color:#94a3b8;border:1px solid rgba(255,255,255,0.05);">
        <i class="bi bi-info-circle"></i>
        <b>Debug:</b> Found <b>
          <?php echo $totalEvents; ?>
        </b> events in database.
        <?php if ($totalEvents == 0): ?>
          <span style="color:#f87171;">No events found! Please add sample data to the events table.</span>
        <?php else: ?>
          <span style="color:#34d399;">Events loaded successfully!</span>
        <?php endif; ?>
      </div>

      <!-- ===== WELCOME SECTION ===== -->
      <section class="welcome rise">
        <div class="row g-4 align-items-center">
          <div class="col-lg-8">
            <span class="eyebrow">School Calendar &middot;
              <?php echo date('F') . ' – ' . date('F', strtotime('+2 months')); ?>
            </span>
            <h2>
              <?php echo $upcomingCount; ?>
              <?php echo $upcomingCount == 1 ? 'event' : 'events'; ?> on the horizon
            </h2>
            <p>From the
              <?php echo !empty($upcomingEvents) ? htmlspecialchars($upcomingEvents[0]['event_name']) : 'upcoming events'; ?>
              to
              <?php echo !empty($upcomingEvents) && count($upcomingEvents) > 1 ? htmlspecialchars(end($upcomingEvents)['event_name']) : 'other school activities'; ?>
              &mdash; registrations, timings and venues for every upcoming school function are listed below.
            </p>
            <div class="quick-chips">
              <a href="notices.php" class="chip"><i class="bi bi-megaphone"></i> Notice Board</a>
              <a href="messages.php" class="chip"><i class="bi bi-envelope"></i> Register Interest</a>
              <a href="timetable.php" class="chip"><i class="bi bi-calendar-week"></i> Timetable</a>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="row g-2">
              <div class="col-6">
                <div class="fact">
                  <small>Upcoming</small>
                  <strong>
                    <?php echo str_pad($upcomingCount, 2, '0', STR_PAD_LEFT); ?>
                  </strong>
                  <span>Next 60 days</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Confirmed</small>
                  <strong>
                    <?php echo str_pad($confirmedCount, 2, '0', STR_PAD_LEFT); ?>
                  </strong>
                  <span>Confirmed</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Next Event</small>
                  <strong>
                    <?php echo !empty($upcomingEvents) ? date('d M', strtotime($upcomingEvents[0]['event_date'])) : 'N/A'; ?>
                  </strong>
                  <span>
                    <?php echo !empty($upcomingEvents) ? htmlspecialchars(substr($upcomingEvents[0]['event_name'], 0, 15)) : 'No events'; ?>
                  </span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Completed</small>
                  <strong>
                    <?php echo str_pad($pastCount, 2, '0', STR_PAD_LEFT); ?>
                  </strong>
                  <span>This session</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== EVENTS GRID ===== -->
      <section class="row g-3 mt-4">

        <?php if (!empty($upcomingEvents)): ?>
          <?php $colors = ['teal', 'info', 'amber', 'violet', 'ok', 'danger']; ?>
          <?php foreach ($upcomingEvents as $index => $event): ?>
            <div class="col-md-6 col-xl-4 rise rise-<?php echo ($index % 6) + 1; ?>">
              <div class="card lift h-100">
                <div class="card-body">
                  <div class="d-flex align-items-start gap-3 mb-3">
                    <span class="subject-ico <?php echo getEventColor($event['event_name']); ?> mb-0"
                      style="width:52px;height:52px;flex:0 0 52px">
                      <i class="bi <?php echo getEventIcon($event['event_name']); ?>"></i>
                    </span>
                    <div class="notice-date" style="background:rgba(255,255,255,0.04);border-radius:10px;padding:6px 12px;">
                      <b>
                        <?php echo date('d', strtotime($event['event_date'])); ?>
                      </b>
                      <small>
                        <?php echo date('M', strtotime($event['event_date'])); ?>
                      </small>
                    </div>
                  </div>
                  <span class="pill <?php echo $event['status_color']; ?>">
                    <?php echo $event['display_status']; ?>
                  </span>
                  <h3 class="mt-2" style="font-size:1.05rem">
                    <?php echo htmlspecialchars($event['event_name']); ?>
                  </h3>
                  <p style="font-size:.82rem;color:#5a6a8a;line-height:1.55">
                    <?php echo htmlspecialchars($event['description'] ?? 'No description available.'); ?>
                  </p>
                  <ul class="meta-list">
                    <li><i class="bi bi-calendar-event"></i> Date <span>
                        <?php echo date('D, d M Y', strtotime($event['event_date'])); ?>
                      </span></li>
                    <li><i class="bi bi-clock"></i> Time <span>
                        <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                      </span></li>
                    <li><i class="bi bi-geo-alt"></i> Location <span>
                        <?php echo htmlspecialchars($event['location']); ?>
                      </span></li>
                    <li><i class="bi bi-person-badge"></i> Organizer <span>
                        <?php echo htmlspecialchars($event['organizer']); ?>
                      </span></li>
                  </ul>
                  <span class="pill <?php echo $event['status_color']; ?>">
                    <?php echo $event['days_left'] ?? 'Upcoming'; ?>
                  </span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12">
            <div class="card lift">
              <div class="card-body" style="text-align:center;padding:60px 20px;">
                <i class="bi bi-calendar2-week" style="font-size:3rem;opacity:0.2;display:block;margin-bottom:16px;"></i>
                <h3 style="font-size:1.2rem;opacity:0.5;">No upcoming events</h3>
                <p style="opacity:0.3;max-width:400px;margin:0 auto;">
                  <?php if ($totalEvents == 0): ?>
                    The events table is empty. Please add sample data to display events.
                  <?php else: ?>
                    Check back later for upcoming school events.
                  <?php endif; ?>
                </p>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </section>

      <!-- ===== EVENT SCHEDULE + PAST EVENTS ===== -->
      <section class="row g-3 mt-1 rise">
        <div class="col-xl-7">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Event Schedule <span class="sub">Chronological listing</span></h3>
            </div>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Location</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($upcomingEvents)): ?>
                    <?php foreach ($upcomingEvents as $event): ?>
                      <tr>
                        <td class="t-strong">
                          <?php echo htmlspecialchars($event['event_name']); ?>
                        </td>
                        <td>
                          <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                        </td>
                        <td>
                          <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                        </td>
                        <td>
                          <?php echo htmlspecialchars($event['location']); ?>
                        </td>
                        <td><span class="pill <?php echo $event['status_color']; ?>">
                            <?php echo $event['display_status']; ?>
                          </span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" style="text-align:center;padding:30px;opacity:0.4;">No events scheduled</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-xl-5">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Past Events <span class="sub">Session
                  <?php echo date('Y'); ?> so far
                </span></h3>
            </div>
            <div class="card-body tight">
              <?php if (!empty($pastEvents)): ?>
                <?php foreach ($pastEvents as $event): ?>
                  <div class="notice-row <?php echo getEventColor($event['event_name']); ?>">
                    <div class="notice-date">
                      <b>
                        <?php echo date('d', strtotime($event['event_date'])); ?>
                      </b>
                      <small>
                        <?php echo date('M', strtotime($event['event_date'])); ?>
                      </small>
                    </div>
                    <div>
                      <h4>
                        <?php echo htmlspecialchars($event['event_name']); ?>
                      </h4>
                      <p>
                        <?php echo htmlspecialchars($event['location']); ?> &middot;
                        <?php echo htmlspecialchars($event['organizer']); ?>
                      </p>
                      <span class="pill p-grey">Completed</span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div style="text-align:center;padding:20px;opacity:0.4;">
                  <i class="bi bi-clock-history" style="font-size:1.5rem;display:block;margin-bottom:8px;"></i>
                  No past events
                </div>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <a href="notices.php" class="btn-outline w-100 justify-content-center"><i class="bi bi-megaphone"></i> See
                Related Notices</a>
            </div>
          </div>
        </div>
      </section>

    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="page-foot">
      <span>&copy; 2026 Crescent Public School &middot; Student Portal</span>
      <span class="d-flex gap-3">
        <a href="notices.php">Help Centre</a>
        <a href="messages.php">Contact Office</a>
        <a href="settings.php">Privacy</a>
      </span>
    </footer>
  </div>

</body>

</html>