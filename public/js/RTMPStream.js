/*
* RTMP streams JS component
*/
"use strict";
/* global streamVars, videojs,  */


// eslint-disable-next-line no-unused-vars
var RTMPStream = {
    init: function () {
        this.initVideo();
    },

    initVideo: function () {
        if (!streamVars.canWatchStream) return;

        var player = videojs('my_video_1', {
            plugins: {
                httpSourceSelector: {
                    default: 'auto'
                }
            },
            autoplay: true,
            preload: "auto",
            controls: true,
            poster: streamVars.streamPoster,
            controlBar: {
                pictureInPictureToggle: false
            }
        });

        player.httpSourceSelector();
        player.play();
    }
};
