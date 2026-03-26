<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: #fff;
            color: #1a1a2e;
        }

        .certificate {
            width: 100%;
            min-height: 595px;
            padding: 40px 60px;
            border: 12px solid #c8a84b;
            outline: 4px solid #1a1a2e;
            outline-offset: -20px;
            position: relative;
            background: #fffdf5;
        }

        .corner {
            position: absolute;
            width: 60px;
            height: 60px;
            border: 3px solid #c8a84b;
        }
        .corner-tl { top: 30px; left: 30px; border-right: none; border-bottom: none; }
        .corner-tr { top: 30px; right: 30px; border-left: none; border-bottom: none; }
        .corner-bl { bottom: 30px; left: 30px; border-right: none; border-top: none; }
        .corner-br { bottom: 30px; right: 30px; border-left: none; border-top: none; }

        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .app-name {
            font-size: 28px;
            font-weight: bold;
            color: #1a1a2e;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        .divider {
            width: 120px;
            height: 2px;
            background: #c8a84b;
            margin: 10px auto;
        }

        .certificate-title {
            font-size: 36px;
            font-style: italic;
            color: #c8a84b;
            margin-top: 6px;
        }

        .sub-title {
            font-size: 12px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #666;
            margin-top: 4px;
        }

        .body {
            text-align: center;
            margin: 30px 0;
        }

        .presented-to {
            font-size: 13px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 8px;
        }

        .student-name {
            font-size: 42px;
            font-style: italic;
            color: #1a1a2e;
            border-bottom: 1px solid #c8a84b;
            display: inline-block;
            padding-bottom: 4px;
            margin-bottom: 20px;
        }

        .completion-text {
            font-size: 14px;
            color: #444;
            line-height: 1.8;
        }

        .course-title {
            font-size: 20px;
            font-weight: bold;
            color: #1a1a2e;
            margin: 6px 0 20px;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 40px;
        }

        .meta-block {
            text-align: center;
            flex: 1;
        }

        .sig-line {
            border-top: 1px solid #1a1a2e;
            width: 160px;
            margin: 0 auto 4px;
        }

        .sig-name {
            font-size: 13px;
            font-weight: bold;
        }

        .sig-label {
            font-size: 10px;
            color: #888;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .seal {
            width: 80px;
            height: 80px;
            border: 3px solid #c8a84b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 10px;
            text-align: center;
            color: #c8a84b;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 9px;
            color: #aaa;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="corner corner-tl"></div>
        <div class="corner corner-tr"></div>
        <div class="corner corner-bl"></div>
        <div class="corner corner-br"></div>

        <div class="header">
            <div class="app-name">{{ $appName }}</div>
            <div class="divider"></div>
            <div class="certificate-title">Certificate of Completion</div>
            <div class="sub-title">This is to proudly certify that</div>
        </div>

        <div class="body">
            <div class="presented-to">This certificate is presented to</div>
            <div class="student-name">{{ $studentName }}</div>
            <div class="completion-text">
                has successfully completed all lessons and requirements of the course
            </div>
            <div class="course-title">{{ $courseTitle }}</div>
            <div class="completion-text">
                on {{ $issuedAt }}
            </div>
        </div>

        <div class="meta">
            <div class="meta-block">
                <div class="sig-line"></div>
                <div class="sig-name">{{ $teacherName }}</div>
                <div class="sig-label">Course Instructor</div>
            </div>

            <div class="meta-block">
                <div class="seal">
                    VERIFIED<br>COMPLETE
                </div>
            </div>

            <div class="meta-block">
                <div class="sig-line"></div>
                <div class="sig-name">{{ $appName }}</div>
                <div class="sig-label">Platform Authority</div>
            </div>
        </div>

        <div class="footer">
            Certificate No: {{ $certificateNumber }} &nbsp;|&nbsp;
            Issued: {{ $issuedAt }} &nbsp;|&nbsp;
            Verify at: {{ $appName }}
        </div>
    </div>
</body>
</html>
