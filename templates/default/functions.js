function popup_window(url, x1, x2, y1, y2)
{
	var xpos;

	xpos = screen.availWidth / 100 * x1;
	ypos = screen.availHeight / 100 * y1;
	xwidth = (screen.availWidth / 100 * (x2 - x1)) - 5;
	yheight = (screen.availHeight / 100 * (y2 - y1)) - 30;

	window.open(url,"list","height=" + yheight + ",width=" + xwidth + ",left=" +xpos + ",ScreenX=" + xpos + ",ScreenY=" + ypos + ",top=" + ypos + ",resizable=yes,menubar=no,status=no,directories=no,toolbar=no,scrollbars=yes");

	return false;
}