
"%PROGRAMFILES(X86)%\Poedit\GettextTools\bin\xgettext.exe" -f potfile.txt --from-code=UTF-8 --language=PHP --keyword=__ -o MArs.pot

"%PROGRAMFILES(X86)%\Poedit\GettextTools\bin\msgmerge.exe" -U de_DE/LC_MESSAGES/MArs.po MArs.pot
"%PROGRAMFILES(X86)%\Poedit\GettextTools\bin\msgmerge.exe" -U en_US/LC_MESSAGES/MArs.po MArs.pot

pause
