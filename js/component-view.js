(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.zoomVideoBehavior = {
    attach(context, settings) {
      const client = ZoomMtgEmbedded.createClient();
      const zoomVideo = settings.zoom_video;
      const meetingSDKElement = document.getElementById("meetingSDKElement");
      const authEndpoint = "/zoom_video/endpoint";
      // Getting SDK Key from zoom_configuration_form's data.
      const sdkKey = zoomVideo.sdk_key;
      const { meetingNumber } = settings.zoom_video;
      const { password } = settings.zoom_video;
      // Getting logged in Drupal user's name and email.
      const { userName } = settings.user;
      const { userEmail } = settings.user;
      const tk = "";
      const zak = "";
      const role = 0;

      function logInfo(info) {
        console.log(info);
      }

      function logError(error) {
        console.log("Error", error);
      }

      async function initializeAndJoinMeeting(signature) {
        try {
          await client.init({
            zoomAppRoot: meetingSDKElement,
            language: "en-US",
          });
          await client.join({
            signature,
            sdkKey,
            meetingNumber,
            password,
            userName,
            userEmail,
            tk,
            zak,
          });
          logInfo("joined successfully");
        } catch (error) {
          logError(error);
        }
      }

      function getSignature() {
        return fetch(authEndpoint, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            meetingNumber,
            role,
          }),
        })
            .then((response) => {
              logInfo(response);
              return response.json();
            })
            .catch((error) => {
              logError(error);
            });
      }

      $(once("zoomVideoWidget", ".join-meeting", context)).click(
          async function () {
            $(this).addClass("hidden");
            const { signature } = await getSignature();
            await initializeAndJoinMeeting(signature);
          }
      );
    },
  };
})(jQuery, Drupal, drupalSettings, once);
