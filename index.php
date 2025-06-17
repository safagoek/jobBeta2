<?php
require_once 'config/db.php';
require_once 'includes/header.php';

// İş ilanlarını veritabanından çek
$stmt = $db->prepare("SELECT * FROM jobs WHERE status = 'active' ORDER BY created_at DESC");
$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lokasyonları benzersiz olarak çek (filtre için)
$stmt = $db->query("SELECT DISTINCT location FROM jobs WHERE status = 'active' ORDER BY location");
$locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>jobBeta2 - Geleceğin İşe Alım Platformu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #ede9fe;
            --primary-dark: #3730a3;
            --primary-darker: #312e81;
            --secondary: #f8fafc;
            --accent: #06d6a0;
            --accent2: #f72585;
            --success: #10b981;
            --success-light: #ecfdf5;
            --danger: #ef4444;
            --danger-light: #fef2f2;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --info: #3b82f6;
            --info-light: #eff6ff;
            --text-dark: #0f172a;
            --text-light: #64748b;
            --text-muted: #94a3b8;
            --border-light: #e2e8f0;
            --border-medium: #cbd5e1;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --bg-glass: rgba(255, 255, 255, 0.8);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius: 16px;
            --radius-lg: 20px;
            --primary-gradient: linear-gradient(135deg, var(--primary), var(--primary-dark));
            --futuristic-gradient: linear-gradient(135deg, var(--primary) 0%, var(--accent) 50%, var(--accent2) 100%);
            --glow-color: rgba(79, 70, 229, 0.4);
            --video-controls-bg: rgba(15, 23, 42, 0.9);
            --video-controls-hover: rgba(15, 23, 42, 0.95);
            --video-controls-text: #ffffff;
            --video-progress-bg: rgba(255, 255, 255, 0.3);
            --video-progress-filled: var(--accent);
            --video-controls-icon-hover: var(--accent);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--bg-light) 0%, #f1f5f9 100%);
            color: var(--text-dark);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-feature-settings: 'cv02', 'cv03', 'cv04', 'cv11';
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero {
            position: relative;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            padding: 120px 0 160px;
            overflow: hidden;
            z-index: 1;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at top, rgba(79, 70, 229, 0.15) 0%, transparent 70%);
            z-index: 2;
        }

        .futuristic-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .shape {
            position: absolute;
            background: linear-gradient(45deg, rgba(79, 70, 229, 0.2), rgba(6, 214, 160, 0.15));
            border-radius: 50%;
            filter: blur(40px);
            animation: float 8s infinite ease-in-out;
        }

        .shape-1 {
            top: 10%;
            left: 10%;
            width: 400px;
            height: 400px;
            animation-delay: 0s;
        }

        .shape-2 {
            top: 60%;
            left: 20%;
            width: 300px;
            height: 300px;
            animation-delay: 2s;
        }

        .shape-3 {
            top: 20%;
            right: 15%;
            width: 350px;
            height: 350px;
            animation-delay: 4s;
        }

        .shape-4 {
            bottom: 10%;
            right: 10%;
            width: 450px;
            height: 450px;
            animation-delay: 6s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) rotate(0deg) scale(1);
                opacity: 0.6;
            }
            33% {
                transform: translateY(-30px) rotate(120deg) scale(1.1);
                opacity: 0.8;
            }
            66% {
                transform: translateY(-60px) rotate(240deg) scale(0.9);
                opacity: 0.4;
            }
        }

        .hero-content-col {
            z-index: 10;
            position: relative;
        }

        .hero-content {
            color: white;
            padding-right: 2rem;
            animation: slideInLeft 1.2s ease-out;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow);
        }

        .hero-badge i {
            margin-right: 0.5rem;
            color: var(--accent);
            font-size: 1rem;
        }

        .hero-title {
            font-size: 4.5rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            letter-spacing: -0.05em;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            line-height: 1.1;
        }

        .text-gradient {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent2) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 20px rgba(6, 214, 160, 0.3));
        }

        .hero-subtitle {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: var(--accent);
            text-shadow: 0 2px 10px rgba(6, 214, 160, 0.3);
        }

        .hero-features {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            font-size: 1.125rem;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.05);
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(10px);
        }

        .feature-item i {
            color: var(--accent);
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn-glow {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 1.25rem 2.5rem;
            font-weight: 700;
            font-size: 1.125rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
            z-index: 1;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-glow::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--futuristic-gradient);
            z-index: -1;
            transition: all 0.3s ease;
            opacity: 0;
        }

        .btn-glow:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(79, 70, 229, 0.6);
            color: white;
        }

        .btn-glow:hover::before {
            opacity: 1;
        }

        /* Video Player */
        .hero-video-col {
            position: relative;
            z-index: 5;
        }

        .video-container {
            position: relative;
            margin: 0 auto;
            max-width: 100%;
            animation: slideInRight 1.5s ease-out;
        }

        .custom-video-player {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            background-color: #000;
            border: 2px solid rgba(255, 255, 255, 0.1);
            width: 100%;
            aspect-ratio: 16 / 9;
            backdrop-filter: blur(10px);
        }

        .custom-video-player video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .big-play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90px;
            height: 90px;
            background: rgba(6, 214, 160, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 3px solid rgba(6, 214, 160, 0.6);
        }

        .big-play-button i {
            font-size: 3.5rem;
            color: white;
            filter: drop-shadow(0 0 15px rgba(6, 214, 160, 0.8));
            margin-left: 5px;
        }

        .big-play-button:hover {
            transform: translate(-50%, -50%) scale(1.15);
            background: rgba(6, 214, 160, 0.4);
            border-color: rgba(6, 214, 160, 0.8);
        }

        /* Video Controls */
        .video-controls {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--video-controls-bg);
            backdrop-filter: blur(20px);
            padding: 1rem 1.25rem;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(10px);
            z-index: 5;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .custom-video-player:hover .video-controls {
            opacity: 1;
            transform: translateY(0);
        }

        .video-progress {
            width: 100%;
            height: 6px;
            position: relative;
            cursor: pointer;
            margin-bottom: 1rem;
        }

        .progress-bar {
            background: var(--video-progress-bg);
            height: 6px;
            width: 100%;
            border-radius: 6px;
            position: relative;
            overflow: hidden;
        }

        .progress-filled {
            background: var(--video-progress-filled);
            height: 100%;
            width: 0;
            border-radius: 6px;
            position: absolute;
            top: 0;
            left: 0;
            transition: width 0.1s linear;
        }

        .progress-tooltip {
            position: absolute;
            top: -30px;
            background: var(--video-controls-bg);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: none;
            z-index: 10;
        }

        .video-progress:hover .progress-tooltip {
            opacity: 1;
        }

        .controls-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .controls-left, .controls-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .video-controls button {
            background: transparent;
            border: none;
            color: var(--video-controls-text);
            font-size: 1.125rem;
            cursor: pointer;
            padding: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border-radius: 50%;
            width: 40px;
            height: 40px;
        }

        .video-controls button:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--video-controls-icon-hover);
            transform: scale(1.1);
        }

        .play-pause {
            position: relative;
        }

        .play-icon, .pause-icon {
            position: absolute;
            transition: all 0.3s ease;
        }

        .pause-icon {
            opacity: 0;
            transform: scale(0.8);
        }

        .playing .play-icon {
            opacity: 0;
            transform: scale(0.8);
        }

        .playing .pause-icon {
            opacity: 1;
            transform: scale(1);
        }

        /* Volume Control */
        .volume-container {
            display: flex;
            align-items: center;
            position: relative;
        }

        .volume-slider {
            width: 0;
            overflow: hidden;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
        }

        .volume-container:hover .volume-slider {
            width: 80px;
        }

        .volume-progress {
            width: 80px;
            height: 4px;
            background: var(--video-progress-bg);
            border-radius: 2px;
            margin-left: 0.75rem;
            position: relative;
            cursor: pointer;
        }

        .volume-progress-filled {
            height: 100%;
            background: var(--video-progress-filled);
            border-radius: 2px;
            width: 100%;
        }

        .volume-high-icon, .volume-low-icon, .volume-muted-icon {
            position: absolute;
            transition: all 0.3s ease;
        }

        .volume-low-icon, .volume-muted-icon {
            opacity: 0;
        }

        .volume-low .volume-high-icon {
            opacity: 0;
        }

        .volume-low .volume-low-icon {
            opacity: 1;
        }

        .volume-muted .volume-high-icon, .volume-muted .volume-low-icon {
            opacity: 0;
        }

        .volume-muted .volume-muted-icon {
            opacity: 1;
        }

        .time {
            color: var(--video-controls-text);
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .speed-btn {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            width: auto;
            padding: 0.5rem 0.75rem;
            border-radius: 1rem;
        }

        .speed-text {
            font-size: 0.875rem;
            font-weight: 600;
        }

        .speed-options {
            position: absolute;
            right: 70px;
            bottom: 60px;
            background: var(--video-controls-bg);
            border-radius: var(--radius);
            padding: 0.75rem;
            display: none;
            flex-direction: column;
            gap: 0.375rem;
            z-index: 20;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
        }

        .speed-option {
            width: 60px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .speed-option[selected] {
            background: rgba(6, 214, 160, 0.3);
            color: var(--accent);
        }

        .fullscreen-btn i.bi-fullscreen-exit {
            display: none;
        }

        .fullscreen .fullscreen-btn i.bi-fullscreen {
            display: none;
        }

        .fullscreen .fullscreen-btn i.bi-fullscreen-exit {
            display: block;
        }

        .hero-wave {
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            z-index: 3;
            filter: drop-shadow(0px -10px 10px rgba(0, 0, 0, 0.1));
        }

        /* Search Section */
        .search-section {
            margin-top: -80px;
            margin-bottom: 4rem;
            position: relative;
            z-index: 10;
        }

        .search-card {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
            animation: slideUp 0.8s ease-out;
            backdrop-filter: blur(20px);
        }

        .search-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(79, 70, 229, 0.15);
        }

        .search-header {
            padding: 2rem 2.5rem;
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, var(--primary-light) 0%, rgba(79, 70, 229, 0.1) 100%);
            position: relative;
        }

        .search-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .search-header h2 {
            font-weight: 800;
            font-size: 1.75rem;
            margin-bottom: 0.75rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .search-header p {
            color: var(--text-light);
            margin-bottom: 0;
            font-size: 1rem;
            line-height: 1.6;
        }

        .search-body {
            padding: 2.5rem;
        }

        .form-control, .form-select {
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            border: 2px solid var(--border-light);
            background: var(--bg-white);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.9375rem;
            font-weight: 500;
            box-shadow: var(--shadow-sm);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1), var(--shadow);
            background: rgba(79, 70, 229, 0.02);
        }

        .input-group-text {
            background: var(--bg-light);
            border: 2px solid var(--border-light);
            color: var(--text-light);
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            font-weight: 700;
            margin-bottom: 0.75rem;
            display: inline-block;
            font-size: 0.9375rem;
            color: var(--text-dark);
            letter-spacing: -0.025em;
        }

        /* Jobs Section */
        .jobs-section {
            padding-bottom: 5rem;
        }

        .jobs-header {
            margin-bottom: 3rem;
            padding: 0 0.5rem;
        }

        .jobs-header h2 {
            font-weight: 800;
            font-size: 2rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.025em;
        }

        .count-badge {
            background: linear-gradient(135deg, var(--success-light) 0%, rgba(16, 185, 129, 0.1) 100%);
            color: var(--success);
            font-weight: 700;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9375rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(16, 185, 129, 0.2);
            transition: all 0.3s ease;
        }

        .count-badge:hover {
            transform: scale(1.05);
        }

        .job-card {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            position: relative;
            backdrop-filter: blur(10px);
        }

        .job-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 4px;
            width: 100%;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .job-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: rgba(79, 70, 229, 0.2);
        }

        .job-card:hover::before {
            transform: scaleX(1);
        }

        .job-card-header {
            padding: 2rem 2rem 1rem;
        }

        .job-card-title {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }

        .job-card-title h3 {
            font-size: 1.375rem;
            font-weight: 700;
            margin: 0 0 0.5rem;
            color: var(--text-dark);
            line-height: 1.3;
            letter-spacing: -0.025em;
        }

        .job-date {
            color: var(--text-light);
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-weight: 600;
            background: var(--bg-light);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            border: 1px solid var(--border-light);
        }

        .job-card-body {
            padding: 0.5rem 2rem 2rem;
            flex: 1;
        }

        .job-tags {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .job-tag {
            display: inline-flex;
            align-items: center;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(79, 70, 229, 0.2);
            gap: 0.375rem;
        }

        .job-tag:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }

        .job-description-container {
            position: relative; 
        }

        .job-description-short, .job-description-full {
            color: var(--text-light);
            font-size: 0.9375rem;
            line-height: 1.7;
            white-space: pre-line;
        }

        .btn-read-more {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.875rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-read-more:hover {
            color: var(--primary-dark);
            transform: translateX(5px);
        }

        .btn-read-more i {
            transition: transform 0.3s ease;
        }

        .btn-read-more.expanded i {
            transform: rotate(180deg);
        }

        .job-card-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-light);
            background: linear-gradient(135deg, var(--bg-light) 0%, rgba(248, 250, 252, 0.8) 100%);
        }

        .btn-apply {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.875rem 2rem;
            font-weight: 700;
            font-size: 0.9375rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: var(--shadow);
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn-apply::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-darker) 100%);
            z-index: -1;
            transition: all 0.3s ease;
            opacity: 0;
        }

        .btn-apply:hover {
            color: white;
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .btn-apply:hover::before {
            opacity: 1;
        }

        .empty-state {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 5rem 3rem;
            text-align: center;
            margin: 3rem 0;
            border: 1px solid var(--border-light);
        }

        .empty-state-icon {
            font-size: 4rem;
            color: var(--primary);
            background: linear-gradient(135deg, var(--primary-light) 0%, rgba(79, 70, 229, 0.1) 100%);
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            box-shadow: var(--shadow-lg);
            border: 3px solid rgba(79, 70, 229, 0.2);
        }

        .empty-state h3 {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-size: 1.5rem;
        }

        .empty-state p {
            color: var(--text-light);
            margin-bottom: 0;
            font-size: 1.125rem;
            line-height: 1.6;
        }

        /* Animations */
        @keyframes slideInLeft {
            from { 
                opacity: 0; 
                transform: translateX(-50px); 
            }
            to { 
                opacity: 1; 
                transform: translateX(0); 
            }
        }

        @keyframes slideInRight {
            from { 
                opacity: 0; 
                transform: translateX(50px); 
            }
            to { 
                opacity: 1; 
                transform: translateX(0); 
            }
        }

        @keyframes slideUp {
            from { 
                opacity: 0; 
                transform: translateY(60px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        .filter-animation {
            animation: pulse 0.6s ease-out;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Selection styles */
        ::selection {
            background: rgba(79, 70, 229, 0.2);
            color: var(--text-dark);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 12px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-light);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 6px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-darker));
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 4rem;
            }
            
            .custom-video-player {
                max-width: 95%;
                margin: 0 auto;
            }
        }

        @media (max-width: 992px) {
            .hero {
                padding: 80px 0 120px;
                text-align: center;
            }
            
            .hero-title {
                font-size: 3.5rem;
            }
            
            .hero-content {
                padding-right: 0;
                margin-bottom: 3rem;
            }
            
            .hero-features {
                align-items: center;
            }
            
            .feature-item {
                justify-content: center;
                text-align: center;
            }
            
            .search-section {
                margin-top: -50px;
            }
            
            .volume-container:hover .volume-slider {
                width: 60px;
            }
            
            .volume-progress {
                width: 60px;
            }
        }

        @media (max-width: 768px) {
            .hero {
                padding: 60px 0 100px;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.25rem;
            }
            
            .hero-actions {
                justify-content: center;
                width: 100%;
            }
            
            .btn-glow {
                width: 100%;
                max-width: 300px;
            }
            
            .search-section {
                margin-top: 2rem;
            }
            
            .search-header, .search-body {
                padding: 1.5rem;
            }
            
            .job-card-title {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .job-date {
                margin-top: 0.5rem;
            }
            
            .job-card-header, .job-card-body, .job-card-footer {
                padding: 1.5rem;
            }
            
            .time {
                display: none;
            }
            
            .video-controls {
                padding: 0.75rem 1rem;
            }
            
            .video-controls button {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }
            
            .controls-left, .controls-right {
                gap: 0.5rem;
            }
            
            .shape-1, .shape-2, .shape-3, .shape-4 {
                width: 200px;
                height: 200px;
            }
        }

        @media (max-width: 480px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-badge {
                font-size: 0.75rem;
                padding: 0.5rem 1rem;
            }
            
            .feature-item {
                font-size: 1rem;
                padding: 0.5rem 1rem;
            }
            
            .empty-state {
                padding: 3rem 1.5rem;
            }
            
            .empty-state-icon {
                width: 100px;
                height: 100px;
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Futuristic Hero Section with Custom Video Player -->
    <section class="hero">
        <div class="futuristic-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
        </div>
        
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-5 hero-content-col">
                    <div class="hero-content">
                        <div class="hero-badge">
                            <i class="bi bi-lightning-charge-fill"></i>
                            <span>Yapay Zeka Destekli</span>
                        </div>
                        <h1 class="hero-title">jobBeta<span class="text-gradient">2</span></h1>
                        <p class="hero-subtitle">Geleceğin İşe Alım Platformu</p>
                        <div class="hero-features">
                            <div class="feature-item">
                                <i class="bi bi-check2-circle"></i>
                                <span>Akıllı eşleştirme</span>
                            </div>
                            <div class="feature-item">
                                <i class="bi bi-check2-circle"></i>
                                <span>Tek tıkla başvuru</span>
                            </div>
                            <div class="feature-item">
                                <i class="bi bi-check2-circle"></i>
                                <span>Gerçek zamanlı takip</span>
                            </div>
                        </div>
                        <div class="hero-actions">
                            <a href="#jobs-section" class="btn btn-glow btn-lg">
                                <span>İş İlanlarını Keşfet</span>
                                <i class="bi bi-arrow-down-circle"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 hero-video-col">
                    <div class="video-container">
                        <div class="custom-video-player">
                            <video id="hero-video" class="video-js">
                                <source src="jobbeta-intro.mp4" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                            
                            <div class="video-controls">
                                <div class="video-progress">
                                    <div class="progress-bar">
                                        <div class="progress-filled"></div>
                                    </div>
                                    <div class="progress-tooltip">00:00</div>
                                </div>
                                
                                <div class="controls-main">
                                    <div class="controls-left">
                                        <button class="play-pause">
                                            <i class="bi bi-play-fill play-icon"></i>
                                            <i class="bi bi-pause-fill pause-icon"></i>
                                        </button>
                                        
                                        <div class="volume-container">
                                            <button class="volume-btn">
                                                <i class="bi bi-volume-up-fill volume-high-icon"></i>
                                                <i class="bi bi-volume-down-fill volume-low-icon"></i>
                                                <i class="bi bi-volume-mute-fill volume-muted-icon"></i>
                                            </button>
                                            <div class="volume-slider">
                                                <div class="volume-progress">
                                                    <div class="volume-progress-filled"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="time">
                                            <span class="current-time">00:00</span>
                                            <span class="time-separator">/</span>
                                            <span class="duration">00:00</span>
                                        </div>
                                    </div>
                                    
                                    <div class="controls-right">
                                        <button class="speed-btn" title="Playback Speed">
                                            <i class="bi bi-speedometer2"></i>
                                            <span class="speed-text">1x</span>
                                        </button>
                                        
                                        <div class="speed-options">
                                            <button class="speed-option" data-speed="0.5">0.5x</button>
                                            <button class="speed-option" data-speed="1" selected>1x</button>
                                            <button class="speed-option" data-speed="1.5">1.5x</button>
                                            <button class="speed-option" data-speed="2">2x</button>
                                        </div>
                                        
                                        <button class="fullscreen-btn">
                                            <i class="bi bi-fullscreen"></i>
                                            <i class="bi bi-fullscreen-exit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="big-play-button">
                                <i class="bi bi-play-circle-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="hero-wave">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                <path fill="#ffffff" fill-opacity="1" d="M0,288L48,272C96,256,192,224,288,197.3C384,171,480,149,576,165.3C672,181,768,235,864,250.7C960,267,1056,245,1152,224C1248,203,1344,181,1392,170.7L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            </svg>
        </div>
    </section>

    <!-- Search Filters Section -->
    <section class="search-section" id="jobs-section">
        <div class="container">
            <div class="search-card">
                <div class="search-header">
                    <h2><i class="bi bi-search"></i>İş Ara</h2>
                    <p>Kriterlerinize uygun pozisyonları filtreleyerek bulun ve kariyerinize yön verin</p>
                </div>
                <div class="search-body">
                    <form id="search-form">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="search-keyword">Anahtar Kelime</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="search-keyword" placeholder="İş unvanı, anahtar kelime...">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="search-location">Lokasyon</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                        <select class="form-select" id="search-location">
                                            <option value="">Tüm Lokasyonlar</option>
                                            <?php foreach ($locations as $location): ?>
                                                <option value="<?= htmlspecialchars($location) ?>"><?= htmlspecialchars($location) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="search-sort">Sıralama</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-sort-down"></i></span>
                                        <select class="form-select" id="search-sort">
                                            <option value="newest">En Yeni</option>
                                            <option value="oldest">En Eski</option>
                                            <option value="az">A-Z</option>
                                            <option value="za">Z-A</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group h-100">
                                    <label class="d-none d-md-block">&nbsp;</label>
                                    <button type="button" class="btn btn-glow w-100 h-md-75" id="btn-filter">
                                        <i class="bi bi-funnel"></i> <span>Filtrele</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Jobs Listing Section -->
    <section class="jobs-section">
        <div class="container">
            <div class="jobs-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="bi bi-briefcase"></i>Açık Pozisyonlar</h2>
                    <div class="job-count">
                        <span class="count-badge"><?= count($jobs) ?> ilan</span>
                    </div>
                </div>
            </div>

            <div class="row" id="jobs-container">
                <?php if (count($jobs) > 0): ?>
                    <?php foreach ($jobs as $job): ?>
                        <div class="col-lg-12 mb-4 job-item">
                            <div class="job-card">
                                <div class="job-card-header">
                                    <div class="job-card-title">
                                        <h3><?= htmlspecialchars($job['title']) ?></h3>
                                        <div class="job-date">
                                            <i class="bi bi-calendar3"></i>
                                            <?= date('d M Y', strtotime($job['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="job-card-body">
                                    <div class="job-tags">
                                        <span class="job-tag">
                                            <i class="bi bi-geo-alt"></i> 
                                            <?= htmlspecialchars($job['location']) ?>
                                        </span>
                                        <span class="job-tag">
                                            <i class="bi bi-briefcase"></i> 
                                            Tam Zamanlı
                                        </span>
                                    </div>
                                    
                                    <div class="job-description-container">
                                        <div class="job-description-short">
                                            <?= mb_strlen($job['description']) > 150 ? nl2br(mb_substr(htmlspecialchars($job['description']), 0, 150)) . '...' : nl2br(htmlspecialchars($job['description'])) ?>
                                        </div>
                                        <div class="job-description-full" style="display: none;">
                                            <?= nl2br(htmlspecialchars($job['description'])) ?>
                                        </div>
                                    </div>
                                    <?php if (mb_strlen($job['description']) > 150): ?>
                                        <button class="btn btn-link btn-sm p-0 mt-2 btn-read-more">
                                            <span>Devamını Oku</span> <i class="bi bi-chevron-down"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="job-card-footer">
                                    <a href="apply.php?job_id=<?= $job['id'] ?>" class="btn btn-apply">
                                        <span>Başvur</span>
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-briefcase-fill"></i>
                            </div>
                            <h3>Henüz İlan Bulunmuyor</h3>
                            <p>Şu anda aktif iş ilanı bulunmamaktadır. Daha sonra tekrar kontrol edin.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Video Player Implementation
            const videoPlayer = document.querySelector('.custom-video-player');
            const video = document.getElementById('hero-video');
            const playPauseButton = document.querySelector('.play-pause');
            const bigPlayButton = document.querySelector('.big-play-button');
            const volumeButton = document.querySelector('.volume-btn');
            const volumeProgress = document.querySelector('.volume-progress');
            const volumeFilled = document.querySelector('.volume-progress-filled');
            const progressBar = document.querySelector('.progress-bar');
            const progressFilled = document.querySelector('.progress-filled');
            const progressTooltip = document.querySelector('.progress-tooltip');
            const currentTimeEl = document.querySelector('.current-time');
            const durationEl = document.querySelector('.duration');
            const speedBtn = document.querySelector('.speed-btn');
            const speedOptions = document.querySelector('.speed-options');
            const speedText = document.querySelector('.speed-text');
            const fullscreenBtn = document.querySelector('.fullscreen-btn');
            
            if (video) {
                let volume = 1;
                video.volume = volume;
                
                function formatTime(seconds) {
                    const minutes = Math.floor(seconds / 60);
                    const secs = Math.floor(seconds % 60);
                    return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                }
                
                function updateProgress() {
                    if (video.currentTime > 0 && video.duration > 0) {
                        const percent = (video.currentTime / video.duration) * 100;
                        progressFilled.style.width = `${percent}%`;
                        currentTimeEl.textContent = formatTime(video.currentTime);
                    } else if (video.duration === 0) {
                        currentTimeEl.textContent = formatTime(0);
                    }
                }
                
                video.addEventListener('loadedmetadata', function() {
                    durationEl.textContent = formatTime(video.duration);
                    updateProgress();
                });
                
                function togglePlay() {
                    if (video.paused) {
                        video.play().catch(error => console.error("Video play failed:", error));
                    } else {
                        video.pause();
                    }
                }
                
                if(playPauseButton) playPauseButton.addEventListener('click', togglePlay);
                if(bigPlayButton) bigPlayButton.addEventListener('click', togglePlay);
                video.addEventListener('click', togglePlay);
                
                video.addEventListener('play', function() {
                    if(videoPlayer) videoPlayer.classList.add('playing');
                    if(bigPlayButton) bigPlayButton.style.display = 'none';
                });
                
                video.addEventListener('pause', function() {
                    if(videoPlayer) videoPlayer.classList.remove('playing');
                    if(bigPlayButton) bigPlayButton.style.display = 'flex';
                });
                
                video.addEventListener('timeupdate', updateProgress);
                
                if(progressBar) {
                    progressBar.addEventListener('click', function(e) {
                        const progressBarRect = progressBar.getBoundingClientRect();
                        const percent = (e.clientX - progressBarRect.left) / progressBarRect.width;
                        if(video.duration) video.currentTime = percent * video.duration;
                    });
                
                    progressBar.addEventListener('mousemove', function(e) {
                        const progressBarRect = progressBar.getBoundingClientRect();
                        const percent = (e.clientX - progressBarRect.left) / progressBarRect.width;
                        const time = video.duration ? (percent * video.duration) : 0;
                        
                        if(progressTooltip) {
                            progressTooltip.textContent = formatTime(time);
                            progressTooltip.style.left = `${e.clientX - progressBarRect.left - (progressTooltip.offsetWidth / 2)}px`;
                            progressTooltip.style.opacity = '1';
                        }
                    });
                
                    progressBar.addEventListener('mouseleave', function() {
                        if(progressTooltip) progressTooltip.style.opacity = '0';
                    });
                }
                
                if(volumeButton) {
                    volumeButton.addEventListener('click', function() {
                        if (video.volume > 0) {
                            volume = video.volume; 
                            video.volume = 0;
                            if(volumeFilled) volumeFilled.style.width = '0';
                            if(videoPlayer) {
                                videoPlayer.classList.add('volume-muted');
                                videoPlayer.classList.remove('volume-low');
                            }
                        } else {
                            video.volume = volume; 
                            if(volumeFilled) volumeFilled.style.width = `${volume * 100}%`;
                            if(videoPlayer) {
                                videoPlayer.classList.remove('volume-muted');
                                if (volume < 0.5 && volume > 0) {
                                    videoPlayer.classList.add('volume-low');
                                } else {
                                    videoPlayer.classList.remove('volume-low');
                                }
                            }
                        }
                    });
                }
                
                if(volumeProgress) {
                    volumeProgress.addEventListener('click', function(e) {
                        const rect = volumeProgress.getBoundingClientRect();
                        let newVolume = (e.clientX - rect.left) / rect.width;
                        newVolume = Math.max(0, Math.min(1, newVolume)); 
                        
                        video.volume = newVolume;
                        volume = newVolume; 
                        if(volumeFilled) volumeFilled.style.width = `${newVolume * 100}%`;
                        
                        if(videoPlayer) {
                            if (newVolume === 0) {
                                videoPlayer.classList.add('volume-muted');
                                videoPlayer.classList.remove('volume-low');
                            } else {
                                videoPlayer.classList.remove('volume-muted');
                                if (newVolume < 0.5) {
                                    videoPlayer.classList.add('volume-low');
                                } else {
                                    videoPlayer.classList.remove('volume-low');
                                }
                            }
                        }
                    });
                }
                
                if(speedBtn) {
                    speedBtn.addEventListener('click', function() {
                        if(speedOptions) speedOptions.style.display = speedOptions.style.display === 'flex' ? 'none' : 'flex';
                    });
                }
                
                document.querySelectorAll('.speed-option').forEach(option => {
                    option.addEventListener('click', function() {
                        const speed = parseFloat(this.dataset.speed);
                        video.playbackRate = speed;
                        if(speedText) speedText.textContent = `${speed}x`;
                        
                        document.querySelectorAll('.speed-option').forEach(opt => opt.removeAttribute('selected'));
                        this.setAttribute('selected', '');
                        if(speedOptions) speedOptions.style.display = 'none';
                    });
                });
                
                document.addEventListener('click', function(e) {
                    if (speedBtn && speedOptions && !speedBtn.contains(e.target) && !speedOptions.contains(e.target)) {
                        speedOptions.style.display = 'none';
                    }
                });
                
                if(fullscreenBtn && videoPlayer) {
                    fullscreenBtn.addEventListener('click', function() {
                        if (!document.fullscreenElement) {
                            videoPlayer.requestFullscreen().catch(err => {
                                console.error(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                            });
                        } else {
                            document.exitFullscreen();
                        }
                    });
                }
                
                document.addEventListener('fullscreenchange', function() {
                    if(videoPlayer) videoPlayer.classList.toggle('fullscreen', !!document.fullscreenElement);
                });
                
                video.addEventListener('ended', function() {
                    if(videoPlayer) videoPlayer.classList.remove('playing');
                    if(bigPlayButton) bigPlayButton.style.display = 'flex';
                    if(progressFilled) progressFilled.style.width = '0%';
                    video.currentTime = 0; 
                });
            } 
            
            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetElement = document.querySelector(targetId);
                    
                    if (targetElement) {
                        let offset = 100; 
                        const fixedNavbar = document.querySelector('.navbar.fixed-top');
                        if (fixedNavbar) { 
                            offset = fixedNavbar.offsetHeight + 20;
                        }
                        window.scrollTo({
                            top: targetElement.offsetTop - offset,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            // Typing effect animation for hero subtitle
            const heroSubtitle = document.querySelector('.hero-subtitle');
            if (heroSubtitle) {
                const text = heroSubtitle.textContent;
                heroSubtitle.textContent = '';
                let i = 0;
                const typeWriter = () => {
                    if (i < text.length) {
                        heroSubtitle.textContent += text.charAt(i);
                        i++;
                        setTimeout(typeWriter, 75);
                    }
                };
                setTimeout(typeWriter, 800);
            }
            
            // Enhanced filter functionality
            const filterButton = document.getElementById('btn-filter');
            if (filterButton) {
                filterButton.addEventListener('click', function() {
                    this.classList.add('filter-animation');
                    setTimeout(() => {
                        this.classList.remove('filter-animation');
                    }, 600);
                    
                    const keywordInput = document.getElementById('search-keyword');
                    const locationInput = document.getElementById('search-location');
                    const sortInput = document.getElementById('search-sort');

                    const keyword = keywordInput ? keywordInput.value.toLowerCase() : '';
                    const location = locationInput ? locationInput.value.toLowerCase() : '';
                    const sort = sortInput ? sortInput.value : 'newest';
                    
                    const jobItems = document.querySelectorAll('.job-item');
                    let visibleJobs = 0;
                    
                    const jobsArray = Array.from(jobItems);
                    
                    // Hide all jobs first with animation
                    jobsArray.forEach((item, index) => {
                        item.style.opacity = '0';
                        item.style.transform = 'translateY(20px)';
                        setTimeout(() => {
                            item.style.display = 'none';
                        }, 300);
                    });
                    
                    setTimeout(() => {
                        jobsArray.forEach((item, index) => {
                            const titleEl = item.querySelector('.job-card-title h3');
                            const title = titleEl ? titleEl.textContent.toLowerCase() : '';
                            
                            const jobLocationTag = item.querySelector('.job-tags span:first-child i.bi-geo-alt');
                            let jobLocation = "";
                            if(jobLocationTag && jobLocationTag.nextSibling) {
                                jobLocation = jobLocationTag.nextSibling.textContent.trim().toLowerCase();
                            }
                            
                            const matchesKeyword = keyword === '' || title.includes(keyword);
                            const matchesLocation = location === '' || jobLocation.includes(location);
                            
                            if (matchesKeyword && matchesLocation) {
                                setTimeout(() => {
                                    item.style.display = 'block';
                                    setTimeout(() => {
                                        item.style.opacity = '1';
                                        item.style.transform = 'translateY(0)';
                                        item.classList.add('filter-animation');
                                        setTimeout(() => item.classList.remove('filter-animation'), 600);
                                    }, 50);
                                }, index * 50); 
                                visibleJobs++;
                            }
                        });
                        
                        // Apply sorting
                        const container = document.getElementById('jobs-container');
                        if (container) {
                            setTimeout(() => {
                                let sortedJobs = jobsArray.filter(item => item.style.display !== 'none');
                                
                                const parseDate = (dateString) => {
                                    const parts = dateString.split(' ');
                                    if (parts.length === 3) {
                                        const day = parseInt(parts[0]);
                                        const monthStr = parts[1];
                                        const year = parseInt(parts[2]);
                                        const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                                        const month = monthNames.indexOf(monthStr);
                                        if (month !== -1) return new Date(year, month, day);
                                    }
                                    return new Date(0);
                                };

                                if (sort === 'oldest') {
                                    sortedJobs.sort((a, b) => {
                                        const dateAEl = a.querySelector('.job-date');
                                        const dateBEl = b.querySelector('.job-date');
                                        const dateA = dateAEl ? parseDate(dateAEl.textContent.trim().replace(/<i[^>]*><\/i>/g, '').trim()) : new Date(0);
                                        const dateB = dateBEl ? parseDate(dateBEl.textContent.trim().replace(/<i[^>]*><\/i>/g, '').trim()) : new Date(0);
                                        return dateA - dateB;
                                    });
                                } else if (sort === 'newest') {
                                    sortedJobs.sort((a, b) => {
                                        const dateAEl = a.querySelector('.job-date');
                                        const dateBEl = b.querySelector('.job-date');
                                        const dateA = dateAEl ? parseDate(dateAEl.textContent.trim().replace(/<i[^>]*><\/i>/g, '').trim()) : new Date(0);
                                        const dateB = dateBEl ? parseDate(dateBEl.textContent.trim().replace(/<i[^>]*><\/i>/g, '').trim()) : new Date(0);
                                        return dateB - dateA;
                                    });
                                } else if (sort === 'az') {
                                    sortedJobs.sort((a, b) => {
                                        const titleAEl = a.querySelector('.job-card-title h3');
                                        const titleBEl = b.querySelector('.job-card-title h3');
                                        const titleA = titleAEl ? titleAEl.textContent : '';
                                        const titleB = titleBEl ? titleBEl.textContent : '';
                                        return titleA.localeCompare(titleB);
                                    });
                                } else if (sort === 'za') {
                                    sortedJobs.sort((a, b) => {
                                        const titleAEl = a.querySelector('.job-card-title h3');
                                        const titleBEl = b.querySelector('.job-card-title h3');
                                        const titleA = titleAEl ? titleAEl.textContent : '';
                                        const titleB = titleBEl ? titleBEl.textContent : '';
                                        return titleB.localeCompare(titleA);
                                    });
                                }
                                
                                sortedJobs.forEach(item => container.appendChild(item)); 
                            }, jobsArray.length * 50 + 200); 
                        }

                        const countBadge = document.querySelector('.count-badge');
                        if(countBadge) {
                            countBadge.classList.add('filter-animation');
                            countBadge.textContent = visibleJobs + ' ilan';
                            setTimeout(() => countBadge.classList.remove('filter-animation'), 600);
                        }
                    }, 400);
                });
            }

            // Enhanced "Read More" functionality for job descriptions
            document.querySelectorAll('.btn-read-more').forEach(button => {
                button.addEventListener('click', function() {
                    const cardBody = this.closest('.job-card-body');
                    if (cardBody) {
                        const shortDesc = cardBody.querySelector('.job-description-short');
                        const fullDesc = cardBody.querySelector('.job-description-full');
                        const span = this.querySelector('span');
                        const icon = this.querySelector('i');

                        this.classList.toggle('expanded');

                        if (this.classList.contains('expanded')) {
                            if(shortDesc) {
                                shortDesc.style.opacity = '0';
                                setTimeout(() => {
                                    shortDesc.style.display = 'none';
                                    if(fullDesc) {
                                        fullDesc.style.display = 'block';
                                        setTimeout(() => {
                                            fullDesc.style.opacity = '1';
                                        }, 50);
                                    }
                                }, 300);
                            }
                            if(span) span.textContent = 'Daha Az Göster';
                            if(icon) icon.classList.replace('bi-chevron-down', 'bi-chevron-up');
                        } else {
                            if(fullDesc) {
                                fullDesc.style.opacity = '0';
                                setTimeout(() => {
                                    fullDesc.style.display = 'none';
                                    if(shortDesc) {
                                        shortDesc.style.display = 'block';
                                        setTimeout(() => {
                                            shortDesc.style.opacity = '1';
                                        }, 50);
                                    }
                                }, 300);
                            }
                            if(span) span.textContent = 'Devamını Oku';
                            if(icon) icon.classList.replace('bi-chevron-up', 'bi-chevron-down');
                        }
                    }
                });
            });

            // Job card hover effects
            document.querySelectorAll('.job-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Parallax effect for hero shapes
            window.addEventListener('scroll', function() {
                const scrolled = window.pageYOffset;
                const rate = scrolled * -0.5;
                
                document.querySelectorAll('.shape').forEach((shape, index) => {
                    const speed = 0.3 + (index * 0.1);
                    shape.style.transform = `translateY(${scrolled * speed}px) rotate(${scrolled * 0.1}deg)`;
                });
            });

            // Enhanced search functionality with real-time filtering
            const searchKeyword = document.getElementById('search-keyword');
            const searchLocation = document.getElementById('search-location');
            
            if (searchKeyword) {
                searchKeyword.addEventListener('input', function() {
                    clearTimeout(this.searchTimeout);
                    this.searchTimeout = setTimeout(() => {
                        filterButton.click();
                    }, 500);
                });
            }
            
            if (searchLocation) {
                searchLocation.addEventListener('change', function() {
                    filterButton.click();
                });
            }

            // Add loading animation to apply buttons
            document.querySelectorAll('.btn-apply').forEach(button => {
                button.addEventListener('click', function(e) {
                    const originalText = this.innerHTML;
                    this.innerHTML = `
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span>Yönlendiriliyor...</span>
                    `;
                    this.style.pointerEvents = 'none';
                    
                    // Allow the navigation to proceed
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.style.pointerEvents = 'auto';
                    }, 2000);
                });
            });

            // Initialize transition styles for job descriptions
            document.querySelectorAll('.job-description-short, .job-description-full').forEach(desc => {
                desc.style.transition = 'opacity 0.3s ease';
                desc.style.opacity = '1';
            });

            // Counter animation for job count badge
            const countBadge = document.querySelector('.count-badge');
            if (countBadge) {
                const finalCount = parseInt(countBadge.textContent);
                let currentCount = 0;
                const increment = Math.ceil(finalCount / 30);
                
                const countAnimation = setInterval(() => {
                    currentCount += increment;
                    if (currentCount >= finalCount) {
                        currentCount = finalCount;
                        clearInterval(countAnimation);
                    }
                    countBadge.textContent = currentCount + ' ilan';
                }, 50);
            }

            // Add intersection observer for animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -100px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe job cards for scroll animations
            document.querySelectorAll('.job-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>

<?php require_once 'includes/footer.php'; ?>