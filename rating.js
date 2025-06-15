// document.addEventListener('DOMContentLoaded', function () {
//     const ratingDiv = document.getElementById('star-rating');
//     if (!ratingDiv || ratingDiv.dataset.rated === '1') {
//         document.getElementById('rating-response').innerText = 'You already rated this post.';
//         return;
//     }

//     const stars = ratingDiv.querySelectorAll('.star');

//     stars.forEach(star => {
//         star.addEventListener('click', function () {
//             const rating = this.getAttribute('data-value');
//             const postId = ratingDiv.getAttribute('data-postid');

//             fetch(starRatingAjax.ajaxurl, {
//                 method: 'POST',
//                 headers: {'Content-Type': 'application/x-www-form-urlencoded'},
//                 body: `action=submit_rating&post_id=${postId}&rating=${rating}`
//             })
//             .then(res => res.text())
//             .then(response => {
//                 document.getElementById('rating-response').innerText = response;
//                 stars.forEach(s => s.classList.remove('selected'));
//                 for (let i = 0; i < rating; i++) {
//                     stars[i].classList.add('selected');
//                 }
//             });
//         });
//     });
// });

document.addEventListener('DOMContentLoaded', function () {
    const ratingDiv = document.getElementById('star-rating');
    if (!ratingDiv || ratingDiv.dataset.rated === '1') return;

    const stars = ratingDiv.querySelectorAll('.star');
    const responseBox = document.getElementById('rating-response');

    stars.forEach(star => {
        star.addEventListener('click', function () {
            const rating = this.dataset.value;
            const postId = ratingDiv.dataset.postid;

            fetch(starRatingAjax.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=submit_rating&post_id=${postId}&rating=${rating}`
            })
            .then(res => res.text())
            .then(response => {
                responseBox.innerText = response;
                stars.forEach(s => s.classList.remove('selected'));
                for (let i = 0; i < rating; i++) stars[i].classList.add('selected');
                ratingDiv.dataset.rated = '1';
                document.getElementById('js-ratingValue2').innerText = 'Thank you!';
            });
        });
    });
const allstars = document.querySelectorAll('.star');

allstars.forEach((star, index) => {
  star.addEventListener('mouseenter', () => {
    for (let i = index; i < allstars.length; i--) {
          if(allstars[i].classList.contains('hovered')){
            continue;
         

        }else{
             allstars[i].classList.add('hovered');
        }
    
    }
  });

  star.addEventListener('mouseleave', () => {
    for (let i = index; i < allstars.length; i--) {

        if(allstars[i].classList.contains('hovered')){
        allstars[i].classList.remove('hovered');

        }else{
            continue;
        }
    }
  });
});

});
