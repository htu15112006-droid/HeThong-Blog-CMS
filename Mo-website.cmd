@echo off
echo Giu cua so nay mo sau khi nhap mat khau Ubuntu.
echo Mo Chrome va vao http://localhost/ sau khi dang nhap SSH thanh cong.
echo Neu da co duong ham cong 80 thi dung cua so cu, khong can chay them.
ssh -p 2849 -o PreferredAuthentications=password -o PubkeyAuthentication=no -o ExitOnForwardFailure=yes -L 127.0.0.1:80:127.0.0.1:80 ubuntu@192.168.181.128
pause
