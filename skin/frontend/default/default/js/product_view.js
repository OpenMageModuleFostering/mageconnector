/**
 * @author Sergey Gozhedrianov
 * @return
 */

function showMore(){
	var moreInfo = $('more_info');
	moreInfo.style.display = 'block';
	var moreLink = $('more_link');
	moreLink.style.display = 'none';
	
}

function hideMore(){
	var moreInfo = $('more_info');
	moreInfo.style.display = 'none';
	var moreLink = $('more_link');
	moreLink.style.display = 'block';
}