@echo off
setlocal
cd /d "%~dp0"
echo CAI CMS STUDIO VAO MAY AO UBUNTU
echo Bat VMware truoc. Nhap mat khau Ubuntu khi duoc hoi, khong phai mat khau GitHub.
echo.
scp -P 2849 -o PreferredAuthentications=password -o PubkeyAuthentication=no "%~dp0cms-studio-upgrade.tar.gz" ubuntu@192.168.181.128:/home/ubuntu/cms-studio-upgrade.tar.gz
if errorlevel 1 goto failed
ssh -p 2849 -o PreferredAuthentications=password -o PubkeyAuthentication=no ubuntu@192.168.181.128 "mkdir -p /home/ubuntu/cms-studio-install && tar -xzf /home/ubuntu/cms-studio-upgrade.tar.gz -C /home/ubuntu/cms-studio-install && cd /home/ubuntu/qtri_csdl_lab && docker compose up -d && bash /home/ubuntu/cms-studio-install/cms-studio-upgrade/install-studio.sh /home/ubuntu/qtri_csdl_lab"
if errorlevel 1 goto failed
echo.
echo DA CAI XONG. Chay Mo-website.cmd va giu cua so SSH mo, roi vao http://localhost/
pause
exit /b 0
:failed
echo.
echo Chua cai xong. Giu lai thong bao loi de kiem tra. Khong xoa volume Docker.
pause
exit /b 1
